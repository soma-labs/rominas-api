<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\withHeaders;

/**
 * An edition whose nomination window is open now, with one propose-or-vote category.
 *
 * @param  array<string, mixed>  $editionOverrides
 * @return array{Edition, Category}
 */
function specialOpenEditionAndCategory(array $editionOverrides = []): array
{
    $edition = Edition::factory()->create(array_merge([
        'status' => EditionStatus::NominationsOpen,
        'nominations_start_at' => now()->subDay(),
        'nominations_end_at' => now()->addWeek(),
    ], $editionOverrides));

    return [$edition, Category::factory()->for($edition)->proposeOrVote()->create()];
}

function specialActingMember(): Member
{
    $member = Member::factory()->active()->create();
    Sanctum::actingAs($member, ['member'], 'member');

    return $member;
}

function specialApprovedEntry(Category $category, string $title = 'Approved Entry'): SpecialEntry
{
    return SpecialEntry::factory()->approved()->create(['category_id' => $category->id, 'title' => $title]);
}

it('shows a member the propose-or-vote categories with their approved entries only', function (): void {
    [$edition, $category] = specialOpenEditionAndCategory();
    Category::factory()->for($edition)->appointed()->create();
    Category::factory()->for($edition)->create();
    $approved = specialApprovedEntry($category);
    SpecialEntry::factory()->create(['category_id' => $category->id]);
    SpecialVote::factory()->count(2)->create(['special_entry_id' => $approved->id]);
    specialActingMember();

    getJson('/api/academy/special-awards')
        ->assertStatus(200)
        ->assertJsonPath('data.edition_id', $edition->id)
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.id', $category->id)
        ->assertJsonPath('data.categories.0.action', null)
        ->assertJsonPath('data.categories.0.proposal', null)
        ->assertExactJsonStructure([
            'data' => [
                'edition_id',
                'nominations_start_at',
                'nominations_end_at',
                'categories' => [['id', 'name', 'description', 'action', 'entry_id', 'proposal', 'entries' => [['id', 'title', 'description']]]],
            ],
        ])
        ->assertJsonPath('data.categories.0.entries.0.id', $approved->id);
});

it('records a proposal as a pending entry carrying the proposer\'s vote', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    $member = specialActingMember();

    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", [
        'title' => 'Maria Tanase',
        'description' => 'A lifetime of song.',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.action', 'proposed')
        ->assertJsonPath('data.categories.0.proposal.title', 'Maria Tanase')
        ->assertJsonPath('data.categories.0.proposal.status', 'pending')
        ->assertJsonCount(0, 'data.categories.0.entries');

    $entry = SpecialEntry::query()->sole();

    expect($entry->status)->toBe(SpecialEntryStatus::Pending)
        ->and($entry->proposed_by_member_id)->toBe($member->id)
        ->and(SpecialVote::query()->forMember($member)->forEntry($entry)->count())->toBe(1);
});

it('requires a title for a proposal', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    specialActingMember();

    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

it('refuses a vote from a member who proposed in the category', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    $other = specialApprovedEntry($category);
    specialActingMember();
    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => 'Mine'])->assertStatus(200);

    putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => $other->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'category' => 'You have proposed an entry in this category, so you cannot vote in it.',
        ]);

    expect(SpecialVote::query()->forEntry($other)->count())->toBe(0);
});

it('refuses a proposal from a member who voted in the category', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    $entry = specialApprovedEntry($category);
    specialActingMember();
    putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => $entry->id])->assertStatus(200);

    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => 'Something Else'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category' => 'You have already proposed or voted in this category.']);

    expect(SpecialEntry::query()->count())->toBe(1);
});

it('lets a member propose in one category and vote in another', function (): void {
    [$edition, $first] = specialOpenEditionAndCategory();
    $second = Category::factory()->for($edition)->proposeOrVote()->create(['position' => 1]);
    $entry = specialApprovedEntry($second);
    specialActingMember();

    postJson("/api/academy/special-awards/categories/{$first->id}/proposal", ['title' => 'Mine'])->assertStatus(200);

    putJson("/api/academy/special-awards/categories/{$second->id}/vote", ['entry_id' => $entry->id])
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.action', 'proposed')
        ->assertJsonPath('data.categories.1.action', 'voted')
        ->assertJsonPath('data.categories.1.entry_id', $entry->id);
});

it('moves a member\'s vote when they choose another entry', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    $first = specialApprovedEntry($category, 'First');
    $second = specialApprovedEntry($category, 'Second');
    $member = specialActingMember();
    putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => $first->id])->assertStatus(200);

    putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => $second->id])
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.entry_id', $second->id);

    expect(SpecialVote::query()->forMember($member)->pluck('special_entry_id')->all())->toBe([$second->id]);
});

it('refuses a vote for an entry that is not approved or not in the category', function (): void {
    [$edition, $category] = specialOpenEditionAndCategory();
    $pending = SpecialEntry::factory()->create(['category_id' => $category->id]);
    $elsewhere = specialApprovedEntry(Category::factory()->for($edition)->proposeOrVote()->create());
    specialActingMember();

    foreach ([$pending->id, $elsewhere->id] as $entryId) {
        putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => $entryId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('entry_id');
    }

    expect(SpecialVote::query()->count())->toBe(0);
});

it('points a member at the existing entry when they propose an approved title again', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    specialApprovedEntry($category, 'Maria Tănase');
    specialActingMember();

    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => ' maria tanase! '])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title' => 'This entry already exists. You can vote for it instead.']);
});

it('deletes a pending proposal when its proposer withdraws it', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    specialActingMember();
    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => 'Mine'])->assertStatus(200);

    deleteJson("/api/academy/special-awards/categories/{$category->id}/proposal")
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.action', null)
        ->assertJsonPath('data.categories.0.proposal', null);

    expect(SpecialEntry::query()->count())->toBe(0)
        ->and(SpecialVote::query()->count())->toBe(0);
});

it('keeps an approved entry for the others when its proposer withdraws', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    $member = specialActingMember();
    $entry = SpecialEntry::factory()->approved()->create([
        'category_id' => $category->id,
        'proposed_by_member_id' => $member->id,
    ]);
    SpecialVote::factory()->create(['special_entry_id' => $entry->id, 'member_id' => $member->id]);
    SpecialVote::factory()->create(['special_entry_id' => $entry->id]);

    deleteJson("/api/academy/special-awards/categories/{$category->id}/proposal")
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.action', null)
        ->assertJsonPath('data.categories.0.entries.0.id', $entry->id);

    expect(SpecialVote::query()->forEntry($entry)->count())->toBe(1)
        ->and(SpecialVote::query()->forMember($member)->count())->toBe(0);
});

it('withdraws a vote, freeing the member to propose', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    $entry = specialApprovedEntry($category);
    specialActingMember();
    putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => $entry->id])->assertStatus(200);

    deleteJson("/api/academy/special-awards/categories/{$category->id}/vote")
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.action', null);

    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => 'Mine'])
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.action', 'proposed');
});

it('does not withdraw the vote of a proposal through the vote route, nor a plain vote through the proposal route', function (): void {
    [$edition, $proposedIn] = specialOpenEditionAndCategory();
    $votedIn = Category::factory()->for($edition)->proposeOrVote()->create();
    $entry = specialApprovedEntry($votedIn);
    specialActingMember();
    postJson("/api/academy/special-awards/categories/{$proposedIn->id}/proposal", ['title' => 'Mine'])->assertStatus(200);
    putJson("/api/academy/special-awards/categories/{$votedIn->id}/vote", ['entry_id' => $entry->id])->assertStatus(200);

    deleteJson("/api/academy/special-awards/categories/{$proposedIn->id}/vote")
        ->assertStatus(422)
        ->assertJsonValidationErrors('category');

    deleteJson("/api/academy/special-awards/categories/{$votedIn->id}/proposal")
        ->assertStatus(422)
        ->assertJsonValidationErrors('category');

    expect(SpecialVote::query()->count())->toBe(2);
});

it('refuses every member action outside the nomination window', function (array $editionOverrides): void {
    [, $category] = specialOpenEditionAndCategory($editionOverrides);
    $entry = specialApprovedEntry($category);
    specialActingMember();

    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => 'Mine'])
        ->assertStatus(422)->assertJsonValidationErrors('nominations');
    putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => $entry->id])
        ->assertStatus(422)->assertJsonValidationErrors('nominations');
    deleteJson("/api/academy/special-awards/categories/{$category->id}/proposal")
        ->assertStatus(422)->assertJsonValidationErrors('nominations');
    deleteJson("/api/academy/special-awards/categories/{$category->id}/vote")
        ->assertStatus(422)->assertJsonValidationErrors('nominations');

    // Reading stays possible, so the member still sees what they did.
    getJson('/api/academy/special-awards')->assertStatus(200);
})->with([
    'nominations closed' => [['status' => EditionStatus::NominationsClosed]],
    'past the deadline' => [['nominations_end_at' => now()->subHour()]],
]);

it('hides a category that is not propose-or-vote from the member routes', function (string $state): void {
    [$edition] = specialOpenEditionAndCategory();
    $category = $state === 'ranked'
        ? Category::factory()->for($edition)->create()
        : Category::factory()->for($edition)->appointed()->create();
    specialActingMember();

    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => 'Mine'])->assertStatus(404);
    putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => 1])->assertStatus(404);
})->with(['ranked', 'appointed']);

it('lists the approved entries alphabetically', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    specialApprovedEntry($category, 'Maria Tanase');
    specialApprovedEntry($category, 'Gica Petrescu');
    specialActingMember();

    getJson('/api/academy/special-awards')
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.entries.0.title', 'Gica Petrescu')
        ->assertJsonPath('data.categories.0.entries.1.title', 'Maria Tanase');
});

it('requires member authentication', function (): void {
    [, $category] = specialOpenEditionAndCategory();

    getJson('/api/academy/special-awards')->assertStatus(401);
    postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => 'Mine'])->assertStatus(401);
});

it('answers a member in Romanian when asked to', function (): void {
    [, $category] = specialOpenEditionAndCategory();
    $entry = specialApprovedEntry($category);
    specialActingMember();
    putJson("/api/academy/special-awards/categories/{$category->id}/vote", ['entry_id' => $entry->id])->assertStatus(200);

    withHeaders(['Accept-Language' => 'ro'])
        ->postJson("/api/academy/special-awards/categories/{$category->id}/proposal", ['title' => 'Mine'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category' => 'Ai propus sau ai votat deja la această categorie.']);
});
