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
use Rominas\Results\Model\SpecialResultEntry;
use Rominas\Roles\Model\Role;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;
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

/**
 * Add to a scorable edition a propose-or-vote category (position 1) whose approved entries hold the given
 * number of member votes, plus a pending entry with one vote that must not count.
 *
 * @param  array<string, int>  $votesByTitle
 */
function resultsSeedSpecialCategory(Edition $edition, array $votesByTitle): Category
{
    $category = Category::factory()->for($edition)->proposeOrVote()->create(['position' => 1]);

    foreach ($votesByTitle as $title => $votes) {
        $entry = SpecialEntry::factory()->approved()->create([
            'category_id' => $category->id,
            'title' => $title,
        ]);
        SpecialVote::factory()->count($votes)->create(['special_entry_id' => $entry->id]);
    }

    $pending = SpecialEntry::factory()->create(['category_id' => $category->id, 'title' => 'Still Pending']);
    SpecialVote::factory()->create(['special_entry_id' => $pending->id]);

    return $category;
}

it('lists a propose-or-vote category by member votes, a tie sharing its position', function (): void {
    actingAsCustodian();
    [$edition, $ranked] = seedScorableCategory();
    $special = resultsSeedSpecialCategory($edition, ['Runner Up' => 1, 'Winner B' => 3, 'Winner A' => 3, 'Nobody' => 0]);

    getJson("/api/admin/editions/{$edition->id}/results")
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.categories')
        ->assertJsonPath('data.categories.0.category_id', $ranked->id)
        ->assertJsonPath('data.categories.0.kind', 'ranked')
        ->assertJsonPath('data.categories.0.entries', [])
        ->assertJsonPath('data.categories.1.category_id', $special->id)
        ->assertJsonPath('data.categories.1.kind', 'propose_or_vote')
        ->assertJsonPath('data.categories.1.nominees', [])
        ->assertJsonPath('data.categories.1.entries', [
            ['title' => 'Winner A', 'votes' => 3, 'position' => 1],
            ['title' => 'Winner B', 'votes' => 3, 'position' => 1],
            ['title' => 'Runner Up', 'votes' => 1, 'position' => 3],
            ['title' => 'Nobody', 'votes' => 0, 'position' => 4],
        ]);
});

it('shows the result entered for an appointed category, without votes', function (): void {
    actingAsCustodian();
    [$edition] = seedScorableCategory();
    $appointed = Category::factory()->for($edition)->appointed()->create(['position' => 1]);
    SpecialEntry::factory()->approved()->create([
        'category_id' => $appointed->id,
        'title' => 'Critics Pick',
        'proposed_by_member_id' => null,
    ]);
    $empty = Category::factory()->for($edition)->appointed()->create(['position' => 2]);

    getJson("/api/admin/editions/{$edition->id}/results")
        ->assertStatus(200)
        ->assertJsonPath('data.categories.1.kind', 'appointed')
        ->assertJsonPath('data.categories.1.entries', [
            ['title' => 'Critics Pick', 'votes' => null, 'position' => 1],
        ])
        ->assertJsonPath('data.categories.2.category_id', $empty->id)
        ->assertJsonPath('data.categories.2.entries', []);
});

it('freezes the special categories in the snapshot, text included', function (): void {
    actingAsCustodian();
    [$edition] = seedScorableCategory(EditionStatus::CommitteeReview);
    $special = resultsSeedSpecialCategory($edition, ['Winner' => 2, 'Second' => 1]);

    app(TransitionEditionAction::class)->execute($edition, EditionStatus::ResultsPublished);

    // What happens to the live rows afterwards must not reach the published results.
    SpecialEntry::query()->forCategory($special)->where('title', 'Winner')->sole()->update(['title' => 'Renamed']);
    SpecialVote::query()->forCategory($special)->delete();

    getJson("/api/admin/editions/{$edition->id}/results")
        ->assertStatus(200)
        ->assertJsonPath('data.source', 'snapshot')
        ->assertJsonPath('data.categories.1.entries.0.title', 'Winner')
        ->assertJsonPath('data.categories.1.entries.0.votes', 2)
        ->assertJsonPath('data.categories.1.entries.1.title', 'Second');

    expect(SpecialResultEntry::query()->count())->toBe(2);
});

it('leaves the member vote counts out of the public results', function (): void {
    [$edition] = seedScorableCategory(EditionStatus::CommitteeReview);
    resultsSeedSpecialCategory($edition, ['Winner' => 2, 'Second' => 1]);

    app(TransitionEditionAction::class)->execute($edition, EditionStatus::ResultsPublished);

    getJson("/api/results/editions/{$edition->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.categories.1.entries.0.title', 'Winner')
        ->assertJsonPath('data.categories.1.entries.0.position', 1)
        ->assertJsonPath('data.categories.1.entries.0.votes', null)
        ->assertJsonPath('data.categories.1.entries.1.votes', null);
});

it('exports the special categories with their member votes', function (): void {
    actingAsCustodian();
    [$edition] = seedScorableCategory();
    resultsSeedSpecialCategory($edition, ['Maria Tanase' => 2]);

    $csv = get("/api/admin/editions/{$edition->id}/results/export")->assertStatus(200)->streamedContent();

    expect($csv)
        ->toContain('final_score,votes')
        ->toContain(',1,,"Maria Tanase",,,,,,2')
        ->not->toContain('Still Pending');
});
