<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Shortlist\Actions\GenerateEditionShortlistsAction;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Actions\LinkNomineeSubmissionAction;
use Rominas\Catalog\NomineeSubmission\Actions\ResolveOrCreateNomineeSubmissionAction;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\Users\Model\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Authenticate as an admin holding the `nomineeSubmissions` permission (management owns catalog
 * reconciliation; roles are not auto-seeded in tests). Uniquely named to avoid colliding with other
 * suites' global Pest helpers.
 */
function reconcileActingAsAdmin(): User
{
    Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'nomineeSubmissions', 'guard_name' => 'web']);

    $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->firstOrFail();
    $admin->givePermissionTo('nomineeSubmissions');

    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    return $user;
}

function reconcileClosedEdition(): Edition
{
    return Edition::factory()->create(['status' => EditionStatus::NominationsClosed]);
}

/**
 * A submitted ballot in the given category whose picks are the given free-text names, each staged as a
 * (deduplicated) pending submission — the ballot as it looks before reconciliation.
 *
 * @param  list<string>  $names
 */
function reconcileBallot(Edition $edition, Category $category, array $names): Nomination
{
    $member = Member::factory()->active()->create();
    $nomination = Nomination::factory()->submitted()->create([
        'member_id' => $member->id,
        'edition_id' => $edition->id,
    ]);

    $resolve = app(ResolveOrCreateNomineeSubmissionAction::class);

    foreach ($names as $index => $name) {
        $submission = $resolve->execute($edition, $category->nominee_type, $name);

        NominationRanking::factory()->create([
            'nomination_id' => $nomination->id,
            'category_id' => $category->id,
            'rank' => $index + 1,
            'nominee_submission_id' => $submission->id,
            'nominee_type' => $category->nominee_type,
            'nominee_id' => $submission->resolved_nominee_id,
        ]);
    }

    return $nomination;
}

it('collapses the same typed name from different members onto one submission', function (): void {
    $edition = reconcileClosedEdition();
    $resolve = app(ResolveOrCreateNomineeSubmissionAction::class);

    $a = $resolve->execute($edition, NomineeType::Artist, 'Taylor Swift');
    $b = $resolve->execute($edition, NomineeType::Artist, 'taylor  swift');

    expect($b->id)->toBe($a->id);
    expect(NomineeSubmission::query()->count())->toBe(1);
});

it('links a submission to an existing catalog entry and backfills every ranking', function (): void {
    reconcileActingAsAdmin();
    $edition = reconcileClosedEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $artist = Artist::factory()->create(['name' => 'Taylor Swift']);

    // Two members both typed the name → one submission, two rankings.
    reconcileBallot($edition, $category, ['Taylor Swift']);
    reconcileBallot($edition, $category, ['taylor swift']);

    $submission = NomineeSubmission::query()->firstOrFail();
    expect($submission->rankings()->count())->toBe(2);

    postJson("/api/admin/editions/{$edition->id}/nominee-submissions/{$submission->id}/link", [
        'nominee_id' => $artist->id,
    ])->assertStatus(200)->assertJsonPath('data.status', 'resolved');

    expect($submission->fresh()->resolved_nominee_id)->toBe($artist->id);
    expect(NominationRanking::query()->where('nominee_submission_id', $submission->id)->whereNull('nominee_id')->count())->toBe(0);
    expect(NominationRanking::query()->where('nominee_id', $artist->id)->count())->toBe(2);
});

it('remembers a resolution for the same name typed later', function (): void {
    $edition = Edition::factory()->create(['status' => EditionStatus::NominationsOpen, 'nominations_start_at' => now()->subDay(), 'nominations_end_at' => now()->addWeek()]);
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $artist = Artist::factory()->create(['name' => 'Adele']);
    $admin = User::factory()->create();

    $submission = app(ResolveOrCreateNomineeSubmissionAction::class)->execute($edition, NomineeType::Artist, 'Adele');
    app(LinkNomineeSubmissionAction::class)->execute($submission, $artist->id, $admin);

    // A later ballot typing the same name reuses the (now resolved) submission.
    reconcileBallot($edition, $category, ['adele']);

    $ranking = NominationRanking::query()->firstOrFail();
    expect($ranking->nominee_submission_id)->toBe($submission->id);
    expect($ranking->nominee_id)->toBe($artist->id);
});

it('creates a new catalog entry from a submission and links it', function (): void {
    reconcileActingAsAdmin();
    $edition = reconcileClosedEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    reconcileBallot($edition, $category, ['Some New Band']);

    $submission = NomineeSubmission::query()->firstOrFail();

    postJson("/api/admin/editions/{$edition->id}/nominee-submissions/{$submission->id}/create")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'resolved');

    $artist = Artist::query()->where('name', 'Some New Band')->firstOrFail();
    expect($submission->fresh()->resolved_nominee_id)->toBe($artist->id);
    expect(NominationRanking::query()->where('nominee_id', $artist->id)->count())->toBe(1);
});

it('refuses to create a duplicate when an entry with the same slug exists', function (): void {
    reconcileActingAsAdmin();
    $edition = reconcileClosedEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    // A canonical row created the production way (CreateArtistAction) has a name-derived slug.
    Artist::factory()->create(['name' => 'Existing Act', 'slug' => 'existing-act']);
    reconcileBallot($edition, $category, ['Existing Act']);

    $submission = NomineeSubmission::query()->firstOrFail();

    postJson("/api/admin/editions/{$edition->id}/nominee-submissions/{$submission->id}/create")
        ->assertStatus(422);
});

it('rejects a submission and leaves its rankings unresolved', function (): void {
    reconcileActingAsAdmin();
    $edition = reconcileClosedEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    reconcileBallot($edition, $category, ['Junk Entry']);

    $submission = NomineeSubmission::query()->firstOrFail();

    postJson("/api/admin/editions/{$edition->id}/nominee-submissions/{$submission->id}/reject", ['note' => 'spam'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'rejected');

    expect($submission->fresh()->status)->toBe(NomineeSubmissionStatus::Rejected);
    expect(NominationRanking::query()->where('nominee_submission_id', $submission->id)->whereNull('nominee_id')->count())->toBe(1);
});

it('refuses a link that would list the same nominee twice on a ballot', function (): void {
    reconcileActingAsAdmin();
    $edition = reconcileClosedEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    $artist = Artist::factory()->create(['name' => 'Duplicate Act']);

    // One ballot typed two different names in the same category.
    reconcileBallot($edition, $category, ['Duplicate Act', 'Duplikate Act']);

    $first = NomineeSubmission::query()->where('normalized_name', 'duplicate-act')->firstOrFail();
    $second = NomineeSubmission::query()->where('normalized_name', 'duplikate-act')->firstOrFail();

    // Resolve the first to the artist, then linking the second to the same artist collides on that ballot.
    postJson("/api/admin/editions/{$edition->id}/nominee-submissions/{$first->id}/link", ['nominee_id' => $artist->id])
        ->assertStatus(200);

    postJson("/api/admin/editions/{$edition->id}/nominee-submissions/{$second->id}/link", ['nominee_id' => $artist->id])
        ->assertStatus(422);
});

it('surfaces the top catalog match as a suggestion', function (): void {
    reconcileActingAsAdmin();
    $edition = reconcileClosedEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    Artist::factory()->create(['name' => 'Taylor Swift']);
    Artist::factory()->create(['name' => 'Adele']);
    reconcileBallot($edition, $category, ['taylor swift']);

    $submission = NomineeSubmission::query()->firstOrFail();

    getJson("/api/admin/editions/{$edition->id}/nominee-submissions/{$submission->id}?with_suggestions=1")
        ->assertStatus(200)
        ->assertJsonPath('data.suggestions.0.name', 'Taylor Swift')
        ->assertJsonPath('data.suggestions.0.score', 100);
});

it('blocks shortlist generation while free-text submissions are pending, then allows it once resolved', function (): void {
    $admin = User::factory()->create();
    $edition = reconcileClosedEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    reconcileBallot($edition, $category, ['One', 'Two', 'Three', 'Four', 'Five']);

    $generate = app(GenerateEditionShortlistsAction::class);

    expect(fn() => $generate->execute($edition))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    // Resolve every pending submission.
    $link = app(LinkNomineeSubmissionAction::class);
    foreach (NomineeSubmission::query()->pending()->get() as $submission) {
        $link->execute($submission, Artist::factory()->create()->id, $admin);
    }

    $entries = $generate->execute($edition->fresh());
    expect($entries)->not->toBeEmpty();
});

it('lists an edition\'s submissions with their ranking counts', function (): void {
    reconcileActingAsAdmin();
    $edition = reconcileClosedEdition();
    $category = Category::factory()->for($edition)->create(['nominee_type' => NomineeType::Artist]);
    reconcileBallot($edition, $category, ['Alpha', 'Beta']);

    getJson("/api/admin/editions/{$edition->id}/nominee-submissions?status=pending")
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.ranking_count', 1);
});

it('404s for a submission that belongs to another edition', function (): void {
    reconcileActingAsAdmin();
    $edition = reconcileClosedEdition();
    $other = reconcileClosedEdition();
    $submission = NomineeSubmission::factory()->create(['edition_id' => $other->id]);

    getJson("/api/admin/editions/{$edition->id}/nominee-submissions/{$submission->id}")
        ->assertStatus(404);
});

it('requires the nomineeSubmissions permission', function (): void {
    $edition = reconcileClosedEdition();
    NomineeSubmission::factory()->create(['edition_id' => $edition->id]);

    // Unauthenticated.
    getJson("/api/admin/editions/{$edition->id}/nominee-submissions")->assertStatus(401);

    // Authenticated but without the permission.
    Sanctum::actingAs(User::factory()->create());
    getJson("/api/admin/editions/{$edition->id}/nominee-submissions")->assertStatus(403);
});
