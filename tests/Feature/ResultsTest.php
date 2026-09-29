<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Actions\TransitionEditionAction;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Permissions\Model\Permission;
use Rominas\Results\Actions\PublishEditionResultsAction;
use Rominas\Results\Model\ResultEntry;
use Rominas\Results\Model\ResultSnapshot;
use Rominas\Roles\Model\Role;
use Rominas\Users\Model\User;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Model\BallotRanking;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/**
 * Authenticate as a custodian — an admin holding only the `results` permission (the extra right to
 * view/export final results before publication). Roles/permissions are not auto-seeded in tests.
 */
function actingAsCustodian(): User
{
    Role::query()->firstOrCreate(['name' => 'custodian', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'results', 'guard_name' => 'web']);

    $custodian = Role::query()->where('name', 'custodian')->where('guard_name', 'web')->firstOrFail();
    $custodian->givePermissionTo('results');

    $user = User::factory()->create();
    $user->assignRole('custodian');

    Sanctum::actingAs($user);

    return $user;
}

/**
 * Seed a scorable category with a clear winner (A) — shortlist of A, B, C ranked identically by both
 * the academy and the public, so A takes position 1 on the 60/40 weighting. Self-contained (its own
 * uniquely-named helpers) so this file runs in isolation without colliding with other suites.
 *
 * @return array{Edition, Category, Artist, Artist, Artist}
 */
function seedScorableCategory(EditionStatus $status = EditionStatus::VotingClosed): array
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

    resultsSeedAcademyRanking($edition, $category, $ids);
    resultsSeedPublicRanking($edition, $category, $ids);

    return [$edition, $category, $a, $b, $c];
}

/**
 * One submitted academy nomination ranking the artists in order (index 0 → rank 1).
 *
 * @param  list<int>  $artistIdsByRank
 */
function resultsSeedAcademyRanking(Edition $edition, Category $category, array $artistIdsByRank): void
{
    $nomination = Nomination::factory()->submitted()->create(['edition_id' => $edition->id]);

    foreach ($artistIdsByRank as $index => $artistId) {
        NominationRanking::factory()->create([
            'nomination_id' => $nomination->id,
            'category_id' => $category->id,
            'rank' => $index + 1,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $artistId,
        ]);
    }
}

/**
 * One submitted public ballot ranking the artists in order (index 0 → rank 1).
 *
 * @param  list<int>  $artistIdsByRank
 */
function resultsSeedPublicRanking(Edition $edition, Category $category, array $artistIdsByRank): void
{
    $ballot = Ballot::factory()->submitted()->create(['edition_id' => $edition->id]);

    foreach ($artistIdsByRank as $index => $artistId) {
        BallotRanking::factory()->create([
            'ballot_id' => $ballot->id,
            'category_id' => $category->id,
            'rank' => $index + 1,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $artistId,
        ]);
    }
}

it('lets a custodian view an edition\'s live results before results are published', function (): void {
    actingAsCustodian();
    [$edition, , $a] = seedScorableCategory();

    getJson("/api/admin/editions/{$edition->id}/results")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.edition_id', $edition->id)
        ->assertJsonPath('data.categories.0.nominees.0.nominee_id', $a->id)
        ->assertJsonPath('data.categories.0.nominees.0.position', 1)
        ->assertJsonPath('data.categories.0.nominees.0.nominee.name', $a->name);

    // Nothing is persisted while the edition's results are unpublished — the view is computed live.
    expect(ResultSnapshot::query()->forEdition($edition)->exists())->toBeFalse();
});

it('forbids a user without the results permission', function (): void {
    Sanctum::actingAs(User::factory()->create());
    [$edition] = seedScorableCategory();

    getJson("/api/admin/editions/{$edition->id}/results")->assertStatus(403);
    getJson("/api/admin/editions/{$edition->id}/results/export")->assertStatus(403);
});

it('refuses to show results before public voting has closed', function (): void {
    actingAsCustodian();
    [$edition] = seedScorableCategory(EditionStatus::VotingOpen);

    getJson("/api/admin/editions/{$edition->id}/results")
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('status');
});

it('freezes an immutable snapshot when the edition\'s results are published', function (): void {
    actingAsCustodian();
    [$edition, , $a] = seedScorableCategory(EditionStatus::CommitteeReview);

    app(TransitionEditionAction::class)->execute($edition, EditionStatus::ResultsPublished);

    expect(ResultSnapshot::query()->forEdition($edition)->count())->toBe(1)
        ->and(ResultEntry::query()->count())->toBe(3);

    // The custodian view now reads from the frozen snapshot, same shape and winner.
    getJson("/api/admin/editions/{$edition->id}/results")
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.nominees.0.nominee_id', $a->id)
        ->assertJsonPath('data.categories.0.nominees.0.position', 1);
});

it('exports the results as CSV', function (): void {
    actingAsCustodian();
    [$edition, , $a] = seedScorableCategory();

    $response = get("/api/admin/editions/{$edition->id}/results/export");

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    expect($response->streamedContent())
        ->toContain('nominee_name')
        ->toContain($a->name);
});

it('serves results publicly once published and 404s before results are published', function (): void {
    [$edition] = seedScorableCategory(EditionStatus::CommitteeReview);

    // Unauthenticated, and no snapshot yet.
    getJson("/api/results/editions/{$edition->id}")->assertStatus(404);

    app(TransitionEditionAction::class)->execute($edition, EditionStatus::ResultsPublished);

    getJson("/api/results/editions/{$edition->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.edition_id', $edition->id)
        ->assertJsonCount(1, 'data.categories');
});

it('replaces the snapshot idempotently on re-publish', function (): void {
    [$edition] = seedScorableCategory();
    $action = app(PublishEditionResultsAction::class);

    $action->execute($edition);
    $action->execute($edition);

    expect(ResultSnapshot::query()->forEdition($edition)->count())->toBe(1)
        ->and(ResultEntry::query()->count())->toBe(3);
});

it('labels live results with their source, algorithm and the edition', function (): void {
    actingAsCustodian();
    [$edition] = seedScorableCategory();

    getJson("/api/admin/editions/{$edition->id}/results")
        ->assertStatus(200)
        ->assertJsonPath('data.source', 'live')
        ->assertJsonPath('data.algorithm', 'attributed')
        ->assertJsonPath('data.published_at', null)
        ->assertJsonPath('data.edition.id', $edition->id)
        ->assertJsonPath('data.edition.name', $edition->name)
        ->assertJsonPath('data.edition.status', 'voting_closed');
});

it('serves the snapshot with its published time and the algorithm it was frozen under', function (): void {
    actingAsCustodian();
    [$edition] = seedScorableCategory(EditionStatus::CommitteeReview);

    app(TransitionEditionAction::class)->execute($edition, EditionStatus::ResultsPublished);

    // Switching the algorithm afterwards must not change how the frozen snapshot is described.
    config(['scoring.algorithm' => 'share']);

    getJson("/api/admin/editions/{$edition->id}/results")
        ->assertStatus(200)
        ->assertJsonPath('data.source', 'snapshot')
        ->assertJsonPath('data.algorithm', 'attributed')
        ->assertJsonPath('data.edition.status', 'results_published')
        ->assertJsonPath('data.published_at', fn(?string $publishedAt): bool => $publishedAt !== null);
});

it('lists only editions whose results are readable, for a custodian', function (): void {
    actingAsCustodian();
    [$closed] = seedScorableCategory();
    $open = Edition::factory()->create(['status' => EditionStatus::VotingOpen]);
    $draft = Edition::factory()->create(['status' => EditionStatus::Draft]);

    $response = getJson('/api/admin/results/editions')->assertStatus(200);

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($closed->id)
        ->not->toContain($open->id)
        ->not->toContain($draft->id);

    $response->assertJsonPath('data.0.published_at', null);
});

it('includes an archived edition that has a snapshot in the results edition list', function (): void {
    actingAsCustodian();
    [$edition] = seedScorableCategory(EditionStatus::CommitteeReview);

    app(TransitionEditionAction::class)->execute($edition, EditionStatus::ResultsPublished);
    $edition->refresh()->forceFill(['status' => EditionStatus::Archived])->save();

    $response = getJson('/api/admin/results/editions')->assertStatus(200);

    expect(collect($response->json('data'))->pluck('id')->all())->toContain($edition->id);
    expect($response->json('data.0.published_at'))->not->toBeNull();
});

it('forbids the results edition list without the results permission', function (): void {
    Sanctum::actingAs(User::factory()->create());

    getJson('/api/admin/results/editions')->assertStatus(403);
});
