<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Catalog\Artist\Model\Artist;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Scoring\Actions\ComputeEditionScoresAction;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Model\BallotRanking;

/**
 * The shortlist defines the contested set for a category.
 *
 * @param  list<int>  $artistIds
 */
function seedShortlistEntries(Edition $edition, Category $category, array $artistIds): void
{
    $position = 1;

    foreach ($artistIds as $artistId) {
        ShortlistEntry::factory()->create([
            'edition_id' => $edition->id,
            'category_id' => $category->id,
            'nominee_type' => NomineeType::Artist,
            'nominee_id' => $artistId,
            'position' => $position++,
        ]);
    }
}

/**
 * One academy nomination ranking the artists in order (index 0 → rank 1). Only submitted ones count.
 *
 * @param  list<int>  $artistIdsByRank
 */
function seedAcademyRanking(Edition $edition, Category $category, array $artistIdsByRank, bool $submitted = true): void
{
    $factory = Nomination::factory();
    $nomination = ($submitted ? $factory->submitted() : $factory)->create(['edition_id' => $edition->id]);

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
 * One public ballot ranking the artists in order (index 0 → rank 1). Only submitted ones count.
 *
 * @param  list<int>  $artistIdsByRank
 */
function seedPublicRanking(Edition $edition, Category $category, array $artistIdsByRank, bool $submitted = true): void
{
    $factory = Ballot::factory();
    $ballot = ($submitted ? $factory->submitted() : $factory)->create(['edition_id' => $edition->id]);

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

function scorableEditionAndCategory(EditionStatus $status = EditionStatus::VotingClosed): array
{
    $edition = Edition::factory()->status($status)->create();
    $category = Category::factory()->create([
        'edition_id' => $edition->id,
        'nominee_type' => NomineeType::Artist,
    ]);

    return [$edition, $category];
}

// These cases exercise the 'share' algorithm; the attributed ladders (the default) get their own case
// below and their unit coverage in AttributedScoreCalculatorTest.
beforeEach(fn() => config(['scoring.algorithm' => 'share']));

it('blends academy and public into a weighted final result', function (): void {
    [$edition, $category] = scorableEditionAndCategory();
    [$a, $b, $c] = Artist::factory()->count(3)->create()->all();

    seedShortlistEntries($edition, $category, [$a->id, $b->id, $c->id]);
    seedAcademyRanking($edition, $category, [$a->id, $b->id, $c->id]);   // academy: A=10 B=8 C=6
    seedPublicRanking($edition, $category, [$b->id, $a->id, $c->id]);    // public:  B=10 A=8 C=6

    $result = app(ComputeEditionScoresAction::class)->execute($edition);

    expect($result->categories)->toHaveCount(1);

    $nominees = $result->categories[0]->nominees;

    // A wins on the 60% academy weight even though B led the public vote. Both curves doubled uniformly,
    // so the shares — and thus finalScore — are unchanged from the original 5/4/3 curves.
    expect($nominees[0]->nomineeId)->toBe($a->id)
        ->and($nominees[0]->position)->toBe(1)
        ->and($nominees[0]->academyPoints)->toBe(10)
        ->and($nominees[0]->publicPoints)->toBe(8)
        ->and($nominees[0]->finalScore)->toBe(round(5520 / 14400, 6))
        ->and($nominees[1]->nomineeId)->toBe($b->id)
        ->and($nominees[2]->nomineeId)->toBe($c->id);
});

it('uses the edition\'s own vote weights', function (): void {
    // Flip the default weighting so the public leader wins instead of the academy leader.
    $edition = Edition::factory()->status(EditionStatus::VotingClosed)->create([
        'academy_vote_weight' => 40,
        'public_vote_weight' => 60,
    ]);
    $category = Category::factory()->create(['edition_id' => $edition->id, 'nominee_type' => NomineeType::Artist]);
    [$a, $b] = Artist::factory()->count(2)->create()->all();

    seedShortlistEntries($edition, $category, [$a->id, $b->id]);
    seedAcademyRanking($edition, $category, [$a->id, $b->id]);   // academy: A=5 B=4
    seedPublicRanking($edition, $category, [$b->id, $a->id]);    // public:  B=5 A=4

    $nominees = app(ComputeEditionScoresAction::class)->execute($edition)->categories[0]->nominees;

    expect($nominees[0]->nomineeId)->toBe($b->id);   // public-heavy weighting → B wins
});

it('counts only submitted nominations and ballots', function (): void {
    [$edition, $category] = scorableEditionAndCategory();
    [$a, $b] = Artist::factory()->count(2)->create()->all();

    seedShortlistEntries($edition, $category, [$a->id, $b->id]);
    seedAcademyRanking($edition, $category, [$a->id, $b->id]);              // submitted: A=10 B=8
    seedAcademyRanking($edition, $category, [$b->id, $a->id], submitted: false); // draft — ignored
    seedPublicRanking($edition, $category, [$a->id, $b->id]);               // submitted: A=10 B=8
    seedPublicRanking($edition, $category, [$b->id, $a->id], submitted: false);  // unsubmitted — ignored

    $nominees = app(ComputeEditionScoresAction::class)->execute($edition)->categories[0]->nominees;

    expect($nominees[0]->nomineeId)->toBe($a->id)
        ->and($nominees[0]->academyPoints)->toBe(10)
        ->and($nominees[0]->publicPoints)->toBe(10);
});

it('renormalizes to academy alone when a category has no public votes', function (): void {
    [$edition, $category] = scorableEditionAndCategory();
    [$a, $b] = Artist::factory()->count(2)->create()->all();

    seedShortlistEntries($edition, $category, [$a->id, $b->id]);
    seedAcademyRanking($edition, $category, [$a->id, $b->id]);   // A=5 B=4, no public votes

    $nominees = app(ComputeEditionScoresAction::class)->execute($edition)->categories[0]->nominees;

    expect($nominees[0]->nomineeId)->toBe($a->id)
        ->and($nominees[0]->publicShare)->toBe(0.0)
        ->and($nominees[0]->finalScore)->toBe(round(5 / 9, 6))   // academyShare, weight renormalized
        ->and($nominees[1]->finalScore)->toBe(round(4 / 9, 6));
});

it('yields an empty result for a category with no shortlist', function (): void {
    [$edition] = scorableEditionAndCategory();

    $result = app(ComputeEditionScoresAction::class)->execute($edition);

    expect($result->categories)->toHaveCount(1)
        ->and($result->categories[0]->nominees)->toBe([]);
});

it('refuses to score before public voting has closed', function (): void {
    [$edition] = scorableEditionAndCategory(EditionStatus::VotingOpen);

    expect(fn() => app(ComputeEditionScoresAction::class)->execute($edition))
        ->toThrow(ValidationException::class);
});

it('scores via the client attributed ladders (the default algorithm)', function (): void {
    config(['scoring.algorithm' => 'attributed']);
    [$edition, $category] = scorableEditionAndCategory();
    [$a, $b, $c] = Artist::factory()->count(3)->create()->all();

    // Academy attributed by shortlist position: A=200 (pos 1), B=150, C=100.
    seedShortlistEntries($edition, $category, [$a->id, $b->id, $c->id]);
    // Public: B=10 A=8 C=6 → public attributed B=150, A=100, C=75.
    seedPublicRanking($edition, $category, [$b->id, $a->id, $c->id]);

    $nominees = app(ComputeEditionScoresAction::class)->execute($edition)->categories[0]->nominees;

    // A (200+100) ties B (150+150) at 300, but wins on the higher academy attributed score (PHAZE 7).
    expect($nominees[0]->nomineeId)->toBe($a->id)
        ->and($nominees[0]->position)->toBe(1)
        ->and($nominees[0]->academyShare)->toBe(200.0)
        ->and($nominees[0]->publicShare)->toBe(100.0)
        ->and($nominees[0]->finalScore)->toBe(300.0)
        ->and($nominees[1]->nomineeId)->toBe($b->id)
        ->and($nominees[1]->finalScore)->toBe(300.0)
        ->and($nominees[2]->nomineeId)->toBe($c->id)
        ->and($nominees[2]->finalScore)->toBe(175.0);
});
