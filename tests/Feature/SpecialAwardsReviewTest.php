<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;
use Rominas\Users\Model\User;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * Authenticate as an admin holding the `specialAwards` permission (roles are not auto-seeded in tests).
 */
function specialReviewActingAsAdmin(): User
{
    $admin = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'specialAwards', 'guard_name' => 'web']);
    $admin->givePermissionTo('specialAwards');

    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    return $user;
}

/**
 * @return array{Edition, Category}
 */
function specialReviewEditionAndCategory(EditionStatus $status = EditionStatus::NominationsClosed): array
{
    $edition = Edition::factory()->status($status)->create();

    return [$edition, Category::factory()->for($edition)->proposeOrVote()->create()];
}

/**
 * A pending proposal with its proposer's vote, as a member's proposal leaves it.
 */
function specialReviewPendingProposal(Category $category, string $title): SpecialEntry
{
    $member = Member::factory()->active()->create();
    $entry = SpecialEntry::factory()->create([
        'category_id' => $category->id,
        'title' => $title,
        'proposed_by_member_id' => $member->id,
    ]);
    SpecialVote::factory()->create(['special_entry_id' => $entry->id, 'member_id' => $member->id]);

    return $entry;
}

it('lists an edition\'s entries for review, filtered by status, without any vote count', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $pending = specialReviewPendingProposal($category, 'Pending One');
    SpecialEntry::factory()->approved()->create(['category_id' => $category->id]);
    SpecialEntry::factory()->create();

    getJson("/api/admin/editions/{$edition->id}/special-entries?status=pending")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pending->id)
        ->assertJsonPath('data.0.proposed_by.id', $pending->proposed_by_member_id)
        ->assertJsonMissingPath('data.0.votes')
        ->assertJsonMissingPath('data.0.votes_count');
});

it('lists only the entries of one kind of category when asked', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $proposal = specialReviewPendingProposal($category, 'A Proposal');
    $appointed = Category::factory()->for($edition)->appointed()->create();
    $result = SpecialEntry::factory()->approved()->create(['category_id' => $appointed->id, 'proposed_by_member_id' => null]);

    getJson("/api/admin/editions/{$edition->id}/special-entries?kind=propose_or_vote")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $proposal->id);

    getJson("/api/admin/editions/{$edition->id}/special-entries?kind=appointed")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $result->id);
});

it('approves a pending proposal, which keeps its proposer\'s vote', function (): void {
    $admin = specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $entry = specialReviewPendingProposal($category, 'Maria Tanase');

    postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/approve")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'approved');

    $entry->refresh();

    expect($entry->status)->toBe(SpecialEntryStatus::Approved)
        ->and($entry->reviewed_by_user_id)->toBe($admin->id)
        ->and(SpecialVote::query()->forEntry($entry)->count())->toBe(1);
});

it('refuses to approve a second spelling of an approved title', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    SpecialEntry::factory()->approved()->create(['category_id' => $category->id, 'title' => 'Maria Tănase']);
    $duplicate = specialReviewPendingProposal($category, 'maria tanase');

    postJson("/api/admin/editions/{$edition->id}/special-entries/{$duplicate->id}/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');

    expect($duplicate->refresh()->status)->toBe(SpecialEntryStatus::Pending);
});

it('rejects a pending proposal with a note and frees its proposer', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $entry = specialReviewPendingProposal($category, 'Not Suitable');

    postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/reject")
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');

    postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/reject", ['note' => 'Not eligible.'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_note', 'Not eligible.');

    expect(SpecialVote::query()->count())->toBe(0);
});

it('refuses to approve or reject an entry that is no longer pending', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $entry = SpecialEntry::factory()->approved()->create(['category_id' => $category->id]);

    postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/approve")
        ->assertStatus(422)->assertJsonValidationErrors('status');
    postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/reject", ['note' => 'Late.'])
        ->assertStatus(422)->assertJsonValidationErrors('status');
});

it('merges a duplicate proposal into the entry that stays, moving its vote', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $target = SpecialEntry::factory()->approved()->create(['category_id' => $category->id, 'title' => 'Maria Tanase']);
    SpecialVote::factory()->count(2)->create(['special_entry_id' => $target->id]);
    $duplicate = specialReviewPendingProposal($category, 'M. Tanase');

    postJson("/api/admin/editions/{$edition->id}/special-entries/{$duplicate->id}/merge", ['target_entry_id' => $target->id])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'merged')
        ->assertJsonPath('data.merged_into_entry_id', $target->id);

    expect(SpecialVote::query()->forEntry($target)->count())->toBe(3)
        ->and(SpecialVote::query()->forEntry($duplicate)->count())->toBe(0);
});

it('merges two approved entries, moving every vote', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $target = SpecialEntry::factory()->approved()->create(['category_id' => $category->id]);
    $duplicate = SpecialEntry::factory()->approved()->create(['category_id' => $category->id]);
    SpecialVote::factory()->count(2)->create(['special_entry_id' => $duplicate->id]);

    postJson("/api/admin/editions/{$edition->id}/special-entries/{$duplicate->id}/merge", ['target_entry_id' => $target->id])
        ->assertStatus(200);

    expect(SpecialVote::query()->forEntry($target)->count())->toBe(2)
        ->and($duplicate->refresh()->status)->toBe(SpecialEntryStatus::Merged);
});

it('refuses a merge into itself, a pending entry or an entry of another category', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $entry = specialReviewPendingProposal($category, 'Duplicate');
    $pending = specialReviewPendingProposal($category, 'Also Pending');
    $elsewhere = SpecialEntry::factory()->approved()->create([
        'category_id' => Category::factory()->for($edition)->proposeOrVote()->create()->id,
    ]);

    foreach ([$entry->id, $pending->id, $elsewhere->id] as $targetId) {
        postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/merge", ['target_entry_id' => $targetId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_entry_id');
    }

    expect($entry->refresh()->status)->toBe(SpecialEntryStatus::Pending);
});

it('shows the approved entries that look like the one under review', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $similar = SpecialEntry::factory()->approved()->create(['category_id' => $category->id, 'title' => 'Maria Tanase']);
    SpecialEntry::factory()->approved()->create(['category_id' => $category->id, 'title' => 'Gica Petrescu']);
    $entry = specialReviewPendingProposal($category, 'Tanase Maria');

    getJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}?with_similar=1")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.similar')
        ->assertJsonPath('data.similar.0.id', $similar->id);
});

it('lets an admin correct the text of an entry', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();
    $entry = specialReviewPendingProposal($category, 'Maria Tanse');

    patchJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}", ['title' => 'Maria Tanase'])
        ->assertStatus(200)
        ->assertJsonPath('data.title', 'Maria Tanase');

    expect($entry->refresh()->normalized_title)->toBe('maria-tanase');
});

it('enters, replaces and clears the result of an appointed category', function (): void {
    specialReviewActingAsAdmin();
    $edition = Edition::factory()->status(EditionStatus::CommitteeReview)->create();
    $category = Category::factory()->for($edition)->appointed()->create();
    $url = "/api/admin/editions/{$edition->id}/categories/{$category->id}/appointed-result";

    putJson($url, ['title' => 'First Pick'])->assertStatus(201)->assertJsonPath('data.status', 'approved');
    putJson($url, ['title' => 'Final Pick'])
        ->assertStatus(200)
        ->assertJsonPath('data.title', 'Final Pick');

    $entry = SpecialEntry::query()->forCategory($category)->sole();

    expect($entry->title)->toBe('Final Pick')
        ->and($entry->proposed_by_member_id)->toBeNull();

    deleteJson($url)->assertStatus(200);

    expect(SpecialEntry::query()->forCategory($category)->count())->toBe(0);
});

it('takes a result entered by hand only for an appointed category', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory();

    putJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/appointed-result", ['title' => 'Winner'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category');

    expect(SpecialEntry::query()->count())->toBe(0);
});

it('freezes the special entries once the results are published', function (): void {
    specialReviewActingAsAdmin();
    [$edition, $category] = specialReviewEditionAndCategory(EditionStatus::ResultsPublished);
    $entry = specialReviewPendingProposal($category, 'Too Late');
    $appointed = Category::factory()->for($edition)->appointed()->create();

    postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/approve")
        ->assertStatus(422)->assertJsonValidationErrors('status');
    postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/reject", ['note' => 'Late.'])
        ->assertStatus(422)->assertJsonValidationErrors('status');
    patchJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}", ['title' => 'Changed'])
        ->assertStatus(422)->assertJsonValidationErrors('status');
    putJson("/api/admin/editions/{$edition->id}/categories/{$appointed->id}/appointed-result", ['title' => 'Winner'])
        ->assertStatus(422)->assertJsonValidationErrors('status');
});

it('404s an entry or category reached through another edition', function (): void {
    specialReviewActingAsAdmin();
    [, $category] = specialReviewEditionAndCategory();
    $entry = specialReviewPendingProposal($category, 'Elsewhere');
    $otherEdition = Edition::factory()->archived()->create();
    $appointed = Category::factory()->appointed()->create(['edition_id' => $category->edition_id]);

    getJson("/api/admin/editions/{$otherEdition->id}/special-entries/{$entry->id}")->assertStatus(404);
    postJson("/api/admin/editions/{$otherEdition->id}/special-entries/{$entry->id}/approve")->assertStatus(404);
    putJson("/api/admin/editions/{$otherEdition->id}/categories/{$appointed->id}/appointed-result", ['title' => 'Winner'])
        ->assertStatus(404);
});

it('requires the specialAwards permission', function (): void {
    [$edition, $category] = specialReviewEditionAndCategory();
    $entry = specialReviewPendingProposal($category, 'Guarded');

    getJson("/api/admin/editions/{$edition->id}/special-entries")->assertStatus(401);

    Sanctum::actingAs(User::factory()->create());

    getJson("/api/admin/editions/{$edition->id}/special-entries")->assertStatus(403);
    postJson("/api/admin/editions/{$edition->id}/special-entries/{$entry->id}/approve")->assertStatus(403);
    putJson("/api/admin/editions/{$edition->id}/categories/{$category->id}/appointed-result", ['title' => 'Winner'])
        ->assertStatus(403);
});
