<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Actions\InvalidateBallotsAction;
use Rominas\FraudMonitoring\DataTransferObjects\InvalidateBallotsData;
use Rominas\FraudMonitoring\Model\InvalidationBatch;
use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\Scoring\Actions\ComputeCategoryScoresAction;
use Rominas\Scoring\DataTransferObjects\NomineeScore;
use Rominas\Users\Model\User;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Model\BallotRanking;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Authenticate as an admin holding the `fraudMonitoring` permission via the given role. Roles and
 * permissions are not auto-seeded in tests. Uniquely-named helpers keep this file runnable in isolation.
 */
function fraudActingAs(string $role): User
{
    Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'fraudMonitoring', 'guard_name' => 'web']);

    $roleModel = Role::query()->where('name', $role)->where('guard_name', 'web')->firstOrFail();
    $roleModel->givePermissionTo('fraudMonitoring');

    $user = User::factory()->create();
    $user->assignRole($role);

    Sanctum::actingAs($user);

    return $user;
}

/**
 * A scorable edition with one category shortlisting three artists, plus one submitted academy nomination
 * ranking them A>B>C. Returns the edition, category and the three artists.
 *
 * @return array{Edition, Category, Artist, Artist, Artist}
 */
function fraudSeedScorableEdition(EditionStatus $status = EditionStatus::VotingClosed): array
{
    $edition = Edition::factory()->status($status)->create();
    $category = Category::factory()->create([
        'edition_id' => $edition->id,
        'nominee_type' => NomineeType::Artist,
    ]);

    [$a, $b, $c] = Artist::factory()->count(3)->create()->all();
    $ids = [$a->id, $b->id, $c->id];

    $position = 1;
    foreach ($ids as $artistId) {
        ShortlistEntry::factory()->create([
            'edition_id' => $edition->id,
            'category_id' => $category->id,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $artistId,
            'position' => $position++,
        ]);
    }

    $nomination = Nomination::factory()->submitted()->create(['edition_id' => $edition->id]);
    foreach ($ids as $index => $artistId) {
        NominationRanking::factory()->create([
            'nomination_id' => $nomination->id,
            'category_id' => $category->id,
            'rank' => $index + 1,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $artistId,
        ]);
    }

    return [$edition, $category, $a, $b, $c];
}

/**
 * One submitted public ballot ranking the given artist ids in order (index 0 → rank 1).
 *
 * @param  list<int>  $artistIdsByRank
 */
function fraudSeedBallot(Edition $edition, Category $category, array $artistIdsByRank, ?string $ipHash = null): Ballot
{
    $ballot = Ballot::factory()->submitted()->create([
        'edition_id' => $edition->id,
        'ip_hash' => $ipHash,
    ]);

    foreach ($artistIdsByRank as $index => $artistId) {
        BallotRanking::factory()->create([
            'ballot_id' => $ballot->id,
            'category_id' => $category->id,
            'rank' => $index + 1,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $artistId,
        ]);
    }

    return $ballot;
}

/**
 * @param  list<NomineeScore>  $nominees
 */
function fraudPublicPointsFor(array $nominees, int $nomineeId): int
{
    foreach ($nominees as $nominee) {
        if ($nominee->nomineeId === $nomineeId) {
            return $nominee->publicPoints;
        }
    }

    return 0;
}

it('lets a fraud monitor and a custodian invalidate votes, but forbids others', function (string $role): void {
    fraudActingAs($role);
    [$edition, $category, $a, $b, $c] = fraudSeedScorableEdition();
    $ballot = fraudSeedBallot($edition, $category, [$a->id, $b->id, $c->id]);

    postJson("/api/admin/editions/{$edition->id}/invalidations", [
        'reason' => 'Coordinated fraudulent voting from a single source.',
        'ballot_ids' => [$ballot->id],
    ])->assertStatus(201)
        ->assertJsonPath('data.edition_id', $edition->id)
        ->assertJsonPath('data.ballots_count', 1);

    expect($ballot->refresh()->invalidation_batch_id)->not->toBeNull();
})->with(['fraud_monitor', 'custodian']);

it('records the acting admin on the batch', function (): void {
    $actor = fraudActingAs('fraud_monitor');
    [$edition, $category, $a, $b, $c] = fraudSeedScorableEdition();
    $ballot = fraudSeedBallot($edition, $category, [$a->id, $b->id, $c->id]);

    postJson("/api/admin/editions/{$edition->id}/invalidations", [
        'reason' => 'Duplicate device fingerprint.',
        'ballot_ids' => [$ballot->id],
    ])->assertStatus(201);

    $batch = InvalidationBatch::query()->forEdition($edition)->firstOrFail();
    expect($batch->invalidated_by)->toBe($actor->id)
        ->and($ballot->refresh()->invalidation_batch_id)->toBe($batch->id);
});

it('forbids an admin without the fraudMonitoring permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->name);
    Sanctum::actingAs($user);

    [$edition, $category, $a, $b, $c] = fraudSeedScorableEdition();
    $ballot = fraudSeedBallot($edition, $category, [$a->id, $b->id, $c->id]);

    getJson("/api/admin/editions/{$edition->id}/ballots")->assertStatus(403);
    postJson("/api/admin/editions/{$edition->id}/invalidations", [
        'reason' => 'Attempted without permission.',
        'ballot_ids' => [$ballot->id],
    ])->assertStatus(403);
});

it('rejects unauthenticated access', function (): void {
    [$edition] = fraudSeedScorableEdition();

    getJson("/api/admin/editions/{$edition->id}/ballots")->assertStatus(401);
    postJson("/api/admin/editions/{$edition->id}/invalidations", [
        'reason' => 'No auth.',
        'ballot_ids' => [1],
    ])->assertStatus(401);
});

it('requires a reason to invalidate votes', function (): void {
    fraudActingAs('fraud_monitor');
    [$edition, $category, $a, $b, $c] = fraudSeedScorableEdition();
    $ballot = fraudSeedBallot($edition, $category, [$a->id, $b->id, $c->id]);

    postJson("/api/admin/editions/{$edition->id}/invalidations", [
        'ballot_ids' => [$ballot->id],
    ])->assertStatus(422)->assertJsonValidationErrorFor('reason');
});

it('422s when no selected ballot is eligible', function (): void {
    fraudActingAs('fraud_monitor');
    [$edition] = fraudSeedScorableEdition();
    // An issued (not submitted) ballot is not eligible for invalidation.
    $issued = Ballot::factory()->create(['edition_id' => $edition->id]);

    postJson("/api/admin/editions/{$edition->id}/invalidations", [
        'reason' => 'Nothing eligible here.',
        'ballot_ids' => [$issued->id],
    ])->assertStatus(422)->assertJsonValidationErrorFor('ballot_ids');

    expect($issued->refresh()->invalidation_batch_id)->toBeNull();
});

it('excludes invalidated ballots from the public scoring tally', function (): void {
    $actor = fraudActingAs('fraud_monitor');
    [$edition, $category, $a, $b, $c] = fraudSeedScorableEdition();

    // Two public ballots: one keeps A first, the other pushes A last.
    fraudSeedBallot($edition, $category, [$a->id, $b->id, $c->id]);
    $suspect = fraudSeedBallot($edition, $category, [$c->id, $b->id, $a->id]);

    $compute = app(ComputeCategoryScoresAction::class);

    // Before: A scores rank-1 (10) + rank-3 (6) = 16 public points.
    $before = $compute->execute($edition, $category);
    expect(fraudPublicPointsFor($before->nominees, $a->id))->toBe(16);

    app(InvalidateBallotsAction::class)->execute(
        $edition,
        new InvalidateBallotsData(ballotIds: [$suspect->id], reason: 'Fraudulent ballot.'),
        $actor,
    );

    // After: only the first ballot counts, so A drops to 10 public points.
    $after = $compute->execute($edition, $category);
    expect(fraudPublicPointsFor($after->nominees, $a->id))->toBe(10);
});

it('lists submitted ballots with a shared-ip fraud signal', function (): void {
    fraudActingAs('fraud_monitor');
    [$edition, $category, $a, $b, $c] = fraudSeedScorableEdition();

    $sharedIp = hash('sha256', '203.0.113.7');
    fraudSeedBallot($edition, $category, [$a->id, $b->id, $c->id], $sharedIp);
    fraudSeedBallot($edition, $category, [$a->id, $b->id, $c->id], $sharedIp);
    fraudSeedBallot($edition, $category, [$a->id, $b->id, $c->id], hash('sha256', '198.51.100.4'));

    $response = getJson("/api/admin/editions/{$edition->id}/ballots")->assertStatus(200);

    $shared = collect($response->json('data'))->firstWhere('ip_hash', $sharedIp);
    $lonely = collect($response->json('data'))->firstWhere('ip_hash', hash('sha256', '198.51.100.4'));

    expect($shared['ip_hash_shared_count'])->toBe(2)
        ->and($shared['invalidated'])->toBeFalse()
        ->and($lonely['ip_hash_shared_count'])->toBe(1);
});
