<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Model\InvalidationBatch;
use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\Users\Model\User;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Model\BallotRanking;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/**
 * Authenticate as an admin holding the `reporting` permission (management owns reporting; roles are not
 * auto-seeded in tests). Uniquely named so this file runs in isolation without colliding with other
 * suites' global Pest helpers.
 */
function reportingActingAsAdmin(): User
{
    Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'reporting', 'guard_name' => 'web']);

    $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->firstOrFail();
    $admin->givePermissionTo('reporting');

    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    return $user;
}

/**
 * The single active (non-archived) edition the reports run against.
 */
function reportingActiveEdition(): Edition
{
    return Edition::factory()->create();
}

function reportingCategory(Edition $edition, string $name): Category
{
    return Category::factory()->create([
        'edition_id' => $edition->id,
        'name' => $name,
    ]);
}

/**
 * A submitted ballot that ranks `$rankings` distinct nominees in `$category`, submitted at
 * `$submittedAt` (defaults to now). When `$invalidated` is true it is attached to an invalidation batch
 * (fraud-cancelled) so it must be excluded from valid vote counts.
 */
function reportingSubmittedBallot(
    Edition $edition,
    Category $category,
    int $rankings = 3,
    ?Carbon $submittedAt = null,
    bool $invalidated = false,
): Ballot {
    $ballot = Ballot::factory()->submitted()->create([
        'edition_id' => $edition->id,
        'submitted_at' => $submittedAt ?? now(),
    ]);

    if ($invalidated) {
        $batch = InvalidationBatch::factory()->create(['edition_id' => $edition->id]);
        $ballot->invalidation_batch_id = $batch->id;
        $ballot->save();
    }

    for ($rank = 1; $rank <= $rankings; $rank++) {
        BallotRanking::factory()->create([
            'ballot_id' => $ballot->id,
            'category_id' => $category->id,
            'rank' => $rank,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => Artist::factory(),
        ]);
    }

    return $ballot;
}

it('lists the available reports in the catalogue', function (): void {
    reportingActingAsAdmin();

    getJson('/api/admin/reports')
        ->assertStatus(200)
        ->assertJsonFragment([
            'key' => 'votes-per-category-per-day',
            'title' => 'Votes per Category per Day',
            'columns' => ['category', 'date', 'votes'],
        ]);
});

it('counts one vote per distinct ballot participating in a category', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    $category = reportingCategory($edition, 'Best Album');

    // Two voters, each ranking three nominees in the category on the same day → 2 votes, not 6.
    reportingSubmittedBallot($edition, $category, rankings: 3);
    reportingSubmittedBallot($edition, $category, rankings: 3);

    getJson('/api/admin/reports/votes-per-category-per-day')
        ->assertStatus(200)
        ->assertJsonPath('data.rows.0.category', 'Best Album')
        ->assertJsonPath('data.rows.0.date', '2026-06-01')
        ->assertJsonPath('data.rows.0.votes', 2)
        ->assertJsonCount(1, 'data.rows');
});

it('excludes fraud-cancelled and unsubmitted ballots', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    $category = reportingCategory($edition, 'Best Song');

    reportingSubmittedBallot($edition, $category);                       // counts
    reportingSubmittedBallot($edition, $category, invalidated: true);    // fraud-cancelled → excluded

    // An issued (never submitted) ballot with rankings → excluded.
    $issued = Ballot::factory()->create(['edition_id' => $edition->id]);
    BallotRanking::factory()->create([
        'ballot_id' => $issued->id,
        'category_id' => $category->id,
        'rank' => 1,
        'nominee_type' => NomineeType::Artist,
        'nominee_id' => Artist::factory(),
    ]);

    getJson('/api/admin/reports/votes-per-category-per-day')
        ->assertStatus(200)
        ->assertJsonPath('data.rows.0.votes', 1)
        ->assertJsonCount(1, 'data.rows');
});

it('groups votes by category and by day', function (): void {
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    $album = reportingCategory($edition, 'Best Album');
    $song = reportingCategory($edition, 'Best Song');

    reportingSubmittedBallot($edition, $album, submittedAt: Carbon::parse('2026-06-01 09:00:00'));
    reportingSubmittedBallot($edition, $album, submittedAt: Carbon::parse('2026-06-02 09:00:00'));
    reportingSubmittedBallot($edition, $song, submittedAt: Carbon::parse('2026-06-01 09:00:00'));

    getJson('/api/admin/reports/votes-per-category-per-day')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data.rows')
        ->assertJsonFragment(['category' => 'Best Album', 'date' => '2026-06-01', 'votes' => 1])
        ->assertJsonFragment(['category' => 'Best Album', 'date' => '2026-06-02', 'votes' => 1])
        ->assertJsonFragment(['category' => 'Best Song', 'date' => '2026-06-01', 'votes' => 1]);
});

it('exports the report as CSV', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    reportingSubmittedBallot($edition, reportingCategory($edition, 'Best Album'));

    $response = get('/api/admin/reports/votes-per-category-per-day/export');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    expect($response->streamedContent())
        ->toContain('category,date,votes')
        ->toContain('Best Album');
});

it('exports the report as XLSX', function (): void {
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    reportingSubmittedBallot($edition, reportingCategory($edition, 'Best Album'));

    $response = get('/api/admin/reports/votes-per-category-per-day/export?format=xlsx');

    $response->assertStatus(200);

    expect($response->headers->get('Content-Type'))
        ->toContain('spreadsheetml.sheet');

    // XLSX is a ZIP archive → its content starts with the "PK" local-file signature.
    expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
});

it('404s for an unknown report key', function (): void {
    reportingActingAsAdmin();

    getJson('/api/admin/reports/not-a-real-report')->assertStatus(404);
});

it('422s when no edition is given and none is active', function (): void {
    reportingActingAsAdmin();

    Edition::factory()->archived()->create();

    getJson('/api/admin/reports/votes-per-category-per-day')->assertStatus(422);
});

it('forbids users without the reporting permission and rejects the unauthenticated', function (): void {
    getJson('/api/admin/reports')->assertStatus(401);

    Sanctum::actingAs(User::factory()->create());
    getJson('/api/admin/reports')->assertStatus(403);
    getJson('/api/admin/reports/votes-per-category-per-day')->assertStatus(403);
});

// --- Second batch: per-entity reports, cancelled votes, time filtering --------------------------

/**
 * A submitted academy nomination that ranks `$entity` at `$rank` in `$category`.
 */
function reportingSubmittedNomination(Edition $edition, Category $category, Artist $entity, int $rank = 1): void
{
    $nomination = Nomination::factory()->submitted()->create(['edition_id' => $edition->id]);

    NominationRanking::factory()->create([
        'nomination_id' => $nomination->id,
        'category_id' => $category->id,
        'rank' => $rank,
        'nominee_type' => NomineeType::Artist,
        'nominee_id' => $entity->id,
    ]);
}

/**
 * A submitted public ballot ranking `$entity` at `$rank` in `$category`; optionally fraud-cancelled.
 */
function reportingPublicVoteFor(Edition $edition, Category $category, Artist $entity, int $rank, bool $invalidated = false): void
{
    $ballot = Ballot::factory()->submitted()->create(['edition_id' => $edition->id]);

    if ($invalidated) {
        $batch = InvalidationBatch::factory()->create(['edition_id' => $edition->id]);
        $ballot->invalidation_batch_id = $batch->id;
        $ballot->save();
    }

    BallotRanking::factory()->create([
        'ballot_id' => $ballot->id,
        'category_id' => $category->id,
        'rank' => $rank,
        'nominee_type' => NomineeType::Artist,
        'nominee_id' => $entity->id,
    ]);
}

it('includes the new reports in the catalogue', function (): void {
    reportingActingAsAdmin();

    getJson('/api/admin/reports')
        ->assertStatus(200)
        ->assertJsonFragment(['key' => 'nominations-per-entity'])
        ->assertJsonFragment(['key' => 'public-votes-per-entity'])
        ->assertJsonFragment(['key' => 'cancelled-votes-per-day']);
});

it('counts academy nominations per entity per category, excluding drafts', function (): void {
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    $category = reportingCategory($edition, 'Best Album');
    $artist = Artist::factory()->create(['name' => 'The Nominee']);

    reportingSubmittedNomination($edition, $category, $artist);   // two members nominate the same entity
    reportingSubmittedNomination($edition, $category, $artist);

    // A draft nomination for the same entity must not count.
    $draft = Nomination::factory()->create(['edition_id' => $edition->id]);
    NominationRanking::factory()->create([
        'nomination_id' => $draft->id,
        'category_id' => $category->id,
        'rank' => 1,
        'nominee_type' => NomineeType::Artist,
        'nominee_id' => $artist->id,
    ]);

    getJson('/api/admin/reports/nominations-per-entity')
        ->assertStatus(200)
        ->assertJsonPath('data.rows.0.category', 'Best Album')
        ->assertJsonPath('data.rows.0.entity', 'The Nominee')
        ->assertJsonPath('data.rows.0.nominations', 2)
        ->assertJsonCount(1, 'data.rows');
});

it('sums rank-weighted public points per entity, excluding cancelled ballots', function (): void {
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    $category = reportingCategory($edition, 'Best Song');
    $artist = Artist::factory()->create(['name' => 'Chart Topper']);

    reportingPublicVoteFor($edition, $category, $artist, rank: 1);                    // 10 pts
    reportingPublicVoteFor($edition, $category, $artist, rank: 2);                    // 8 pts
    reportingPublicVoteFor($edition, $category, $artist, rank: 1, invalidated: true); // excluded

    getJson('/api/admin/reports/public-votes-per-entity')
        ->assertStatus(200)
        ->assertJsonPath('data.rows.0.entity', 'Chart Topper')
        ->assertJsonPath('data.rows.0.points', 18)
        ->assertJsonCount(1, 'data.rows');
});

it('counts cancelled ballots per day', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    $category = reportingCategory($edition, 'Best Album');

    reportingSubmittedBallot($edition, $category, invalidated: true);
    reportingSubmittedBallot($edition, $category, invalidated: true);
    reportingSubmittedBallot($edition, $category);   // valid → excluded

    getJson('/api/admin/reports/cancelled-votes-per-day')
        ->assertStatus(200)
        ->assertJsonPath('data.rows.0.date', '2026-06-01')
        ->assertJsonPath('data.rows.0.cancelled', 2)
        ->assertJsonCount(1, 'data.rows');
});

it('filters a report to the given date window', function (): void {
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    $category = reportingCategory($edition, 'Best Album');

    reportingSubmittedBallot($edition, $category, submittedAt: Carbon::parse('2026-06-01 09:00:00'));
    reportingSubmittedBallot($edition, $category, submittedAt: Carbon::parse('2026-06-05 09:00:00'));

    getJson('/api/admin/reports/votes-per-category-per-day?from=2026-06-04&to=2026-06-06')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.rows')
        ->assertJsonPath('data.rows.0.date', '2026-06-05');
});

it('exports a per-entity report as CSV and XLSX', function (): void {
    reportingActingAsAdmin();

    $edition = reportingActiveEdition();
    $category = reportingCategory($edition, 'Best Album');
    $artist = Artist::factory()->create(['name' => 'Export Star']);
    reportingSubmittedNomination($edition, $category, $artist);

    $csv = get('/api/admin/reports/nominations-per-entity/export');
    $csv->assertStatus(200)->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    expect($csv->streamedContent())
        ->toContain('category,entity_type,entity,nominations')
        ->toContain('Export Star');

    $xlsx = get('/api/admin/reports/nominations-per-entity/export?format=xlsx');
    $xlsx->assertStatus(200);
    expect($xlsx->headers->get('Content-Type'))->toContain('spreadsheetml.sheet');
});
