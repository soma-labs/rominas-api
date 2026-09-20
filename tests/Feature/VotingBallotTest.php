<?php

declare(strict_types=1);

use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Voting\Enums\BallotStatus;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Model\BallotRanking;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * A voting-open edition whose window contains "now" (see also VotingLinkRequestTest::votingOpenEdition,
 * a distinct global helper — Pest test-file functions are global, so this one is uniquely named).
 */
function openVotingEdition(EditionStatus $status = EditionStatus::VotingOpen): Edition
{
    $now = now();

    return Edition::factory()->status($status)->create([
        'starts_at' => $now->copy()->subMonths(2),
        'nominations_start_at' => $now->copy()->subWeeks(6),
        'nominations_end_at' => $now->copy()->subWeeks(3),
        'voting_start_at' => $now->copy()->subDay(),
        'voting_end_at' => $now->copy()->addWeek(),
        'ends_at' => $now->copy()->addMonths(2),
    ]);
}

/**
 * A category on the edition with a shortlist of the given artist ids (position order).
 *
 * @param  list<int>  $artistIds
 */
function categoryWithShortlist(Edition $edition, array $artistIds): Category
{
    $category = Category::factory()->create([
        'edition_id' => $edition->id,
        'nominee_type' => NomineeType::Artist,
    ]);

    foreach ($artistIds as $index => $artistId) {
        ShortlistEntry::factory()->create([
            'edition_id' => $edition->id,
            'category_id' => $category->id,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $artistId,
            'points' => 10 - $index,
            'position' => $index + 1,
        ]);
    }

    return $category;
}

function issuedBallot(Edition $edition, string $token): Ballot
{
    return Ballot::factory()->create([
        'edition_id' => $edition->id,
        'token_hash' => hash('sha256', $token),
        'status' => BallotStatus::Issued,
        'expires_at' => $edition->voting_end_at,
    ]);
}

it('loads the shortlist ballot for a valid token', function (): void {
    $edition = openVotingEdition();
    $artists = Artist::factory()->count(5)->create();
    $category = categoryWithShortlist($edition, $artists->pluck('id')->all());
    issuedBallot($edition, 'valid-token');

    // The voter sees all five shortlisted nominees (they pick three of them).
    getJson('/api/voting/ballot?token=valid-token')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.categories.0.id', $category->id)
        ->assertJsonCount(5, 'data.categories.0.nominees');
});

it('presents the shortlist nominees in alphabetical order', function (): void {
    $edition = openVotingEdition();
    $charlie = Artist::factory()->create(['name' => 'Charlie']);
    $alice = Artist::factory()->create(['name' => 'Alice']);
    $bob = Artist::factory()->create(['name' => 'Bob']);
    // Seed the shortlist in a non-alphabetical position order to prove the resource re-sorts.
    categoryWithShortlist($edition, [$charlie->id, $bob->id, $alice->id]);
    issuedBallot($edition, 'valid-token');

    getJson('/api/voting/ballot?token=valid-token')
        ->assertStatus(200)
        ->assertJsonPath('data.categories.0.nominees.0.nominee.name', 'Alice')
        ->assertJsonPath('data.categories.0.nominees.1.nominee.name', 'Bob')
        ->assertJsonPath('data.categories.0.nominees.2.nominee.name', 'Charlie');
});

it('casts a three-of-five ballot and consumes the link', function (): void {
    $edition = openVotingEdition();
    $artists = Artist::factory()->count(5)->create();
    $category = categoryWithShortlist($edition, $artists->pluck('id')->all());
    $ballot = issuedBallot($edition, 'valid-token');

    postJson('/api/voting/ballot', [
        'token' => 'valid-token',
        'categories' => [
            ['category_id' => $category->id, 'nominees' => [$artists[3]->id, $artists[0]->id, $artists[2]->id]],
        ],
    ])->assertStatus(200)->assertJsonPath('success', true);

    expect(BallotRanking::query()->where('ballot_id', $ballot->id)->count())->toBe(3);
    // Rank order follows submission order (index 0 = rank 1).
    expect(BallotRanking::query()->where('ballot_id', $ballot->id)->where('rank', 1)->value('nominee_id'))
        ->toBe($artists[3]->id);

    $ballot->refresh();
    expect($ballot->status)->toBe(BallotStatus::Submitted);
    expect($ballot->submitted_at)->not->toBeNull();
    expect($ballot->ip_hash)->not->toBeNull();
});

it('rejects picking fewer than three nominees', function (): void {
    $edition = openVotingEdition();
    $artists = Artist::factory()->count(5)->create();
    $category = categoryWithShortlist($edition, $artists->pluck('id')->all());
    issuedBallot($edition, 'valid-token');

    postJson('/api/voting/ballot', [
        'token' => 'valid-token',
        'categories' => [
            ['category_id' => $category->id, 'nominees' => [$artists[0]->id, $artists[1]->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrorFor('categories');

    expect(BallotRanking::query()->count())->toBe(0);
});

it('rejects picking more than three nominees', function (): void {
    $edition = openVotingEdition();
    $artists = Artist::factory()->count(5)->create();
    $category = categoryWithShortlist($edition, $artists->pluck('id')->all());
    issuedBallot($edition, 'valid-token');

    postJson('/api/voting/ballot', [
        'token' => 'valid-token',
        'categories' => [
            ['category_id' => $category->id, 'nominees' => [$artists[0]->id, $artists[1]->id, $artists[2]->id, $artists[3]->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrorFor('categories.0.nominees');

    expect(BallotRanking::query()->count())->toBe(0);
});

it('rejects a nominee that is not on the shortlist', function (): void {
    $edition = openVotingEdition();
    $artists = Artist::factory()->count(5)->create();
    $category = categoryWithShortlist($edition, $artists->pluck('id')->all());
    $outsider = Artist::factory()->create();
    issuedBallot($edition, 'valid-token');

    postJson('/api/voting/ballot', [
        'token' => 'valid-token',
        'categories' => [
            ['category_id' => $category->id, 'nominees' => [$artists[0]->id, $artists[1]->id, $outsider->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrorFor('categories');
});

it('rejects a category that is not in the edition', function (): void {
    $edition = openVotingEdition();
    $artists = Artist::factory()->count(5)->create();
    categoryWithShortlist($edition, $artists->pluck('id')->all());
    issuedBallot($edition, 'valid-token');
    // A category on a different (archived) edition — archived keeps the voting edition the sole active one.
    $otherEdition = Edition::factory()->archived()->create();
    $otherCategory = Category::factory()->create(['edition_id' => $otherEdition->id, 'nominee_type' => NomineeType::Artist]);

    postJson('/api/voting/ballot', [
        'token' => 'valid-token',
        'categories' => [
            ['category_id' => $otherCategory->id, 'nominees' => [$artists[0]->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrorFor('categories');
});

it('requires at least one category', function (): void {
    $edition = openVotingEdition();
    issuedBallot($edition, 'valid-token');

    postJson('/api/voting/ballot', ['token' => 'valid-token', 'categories' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('categories');
});

it('is single-use: a submitted ballot cannot be loaded or resubmitted', function (): void {
    $edition = openVotingEdition();
    $artists = Artist::factory()->count(5)->create();
    $category = categoryWithShortlist($edition, $artists->pluck('id')->all());
    issuedBallot($edition, 'valid-token');

    $payload = [
        'token' => 'valid-token',
        'categories' => [
            ['category_id' => $category->id, 'nominees' => [$artists[0]->id, $artists[1]->id, $artists[2]->id]],
        ],
    ];

    postJson('/api/voting/ballot', $payload)->assertStatus(200);

    // The consumed link no longer loads or accepts a second submission.
    getJson('/api/voting/ballot?token=valid-token')->assertStatus(422);
    postJson('/api/voting/ballot', $payload)->assertStatus(422);

    expect(BallotRanking::query()->count())->toBe(3);
});

it('rejects an unknown token', function (): void {
    openVotingEdition();

    getJson('/api/voting/ballot?token=nope')->assertStatus(422);
    postJson('/api/voting/ballot', ['token' => 'nope', 'categories' => [['category_id' => 1, 'nominees' => [1]]]])
        ->assertStatus(422);
});

it('rejects voting when the window is closed', function (): void {
    $edition = openVotingEdition(EditionStatus::VotingClosed);
    issuedBallot($edition, 'valid-token');

    getJson('/api/voting/ballot?token=valid-token')->assertStatus(422);
});
