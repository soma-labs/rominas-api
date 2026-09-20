<?php

declare(strict_types=1);

use Rominas\Catalog\Enums\NomineeType;
use Rominas\Scoring\Support\AttributedScoreCalculator;

/**
 * @param  list<array{id: int, academy_position: int, public_points: int}>  $rows
 * @return list<array{nominee_type: NomineeType, nominee_id: int, academy_points: int, public_points: int, academy_position: int}>
 */
function attributedTallies(array $rows): array
{
    return array_map(static fn(array $row): array => [
        'nominee_type' => NomineeType::Artist,
        'nominee_id' => $row['id'],
        'academy_points' => 0, // raw academy points are display-only for this algorithm
        'public_points' => $row['public_points'],
        'academy_position' => $row['academy_position'],
    ], $rows);
}

function attributedCalculator(): AttributedScoreCalculator
{
    return new AttributedScoreCalculator(
        academyLadder: [200, 150, 100, 75, 50],
        publicLadder: [150, 100, 75, 50, 25],
        precision: 6,
    );
}

it('adds the academy and public attributed scores and ranks by the total', function (): void {
    // Academy attributed by shortlist position; public attributed by public-points rank (ties break
    // academy-first). N2 leads the public tie over N3, so it takes the top public score.
    $tallies = attributedTallies([
        ['id' => 1, 'academy_position' => 1, 'public_points' => 6],   // academy 200, public rank 4 → 50  = 250
        ['id' => 2, 'academy_position' => 2, 'public_points' => 10],  // academy 150, public rank 1 → 150 = 300
        ['id' => 3, 'academy_position' => 3, 'public_points' => 10],  // academy 100, public rank 2 → 100 = 200
        ['id' => 4, 'academy_position' => 4, 'public_points' => 0],   // academy 75,  public rank 5 → 25  = 100
        ['id' => 5, 'academy_position' => 5, 'public_points' => 8],   // academy 50,  public rank 3 → 75  = 125
    ]);

    $scores = attributedCalculator()->rank($tallies, 60, 40);

    expect($scores)->toHaveCount(5)
        ->and($scores[0]->nomineeId)->toBe(2)
        ->and($scores[0]->position)->toBe(1)
        ->and($scores[0]->academyShare)->toBe(150.0)   // academy attributed
        ->and($scores[0]->publicShare)->toBe(150.0)    // public attributed
        ->and($scores[0]->finalScore)->toBe(300.0)     // total
        ->and($scores[1]->nomineeId)->toBe(1)          // 250
        ->and($scores[2]->nomineeId)->toBe(3)          // 200
        ->and($scores[3]->nomineeId)->toBe(5)          // 125
        ->and($scores[4]->nomineeId)->toBe(4);         // 100
});

it('breaks a total tie in favour of the higher academy attributed score', function (): void {
    // Both total 300; A has the higher academy attributed (200 vs 150), so A wins (PHAZE 7).
    $tallies = attributedTallies([
        ['id' => 1, 'academy_position' => 1, 'public_points' => 5],   // academy 200, public rank 2 → 100 = 300
        ['id' => 2, 'academy_position' => 2, 'public_points' => 10],  // academy 150, public rank 1 → 150 = 300
    ]);

    $scores = attributedCalculator()->rank($tallies, 60, 40);

    expect($scores[0]->nomineeId)->toBe(1)
        ->and($scores[0]->finalScore)->toBe(300.0)
        ->and($scores[1]->nomineeId)->toBe(2)
        ->and($scores[1]->finalScore)->toBe(300.0);
});

it('uses only the first ladder entries when fewer than five are shortlisted', function (): void {
    $tallies = attributedTallies([
        ['id' => 1, 'academy_position' => 1, 'public_points' => 4],   // academy 200, public rank 2 → 100 = 300
        ['id' => 2, 'academy_position' => 2, 'public_points' => 9],   // academy 150, public rank 1 → 150 = 300
        ['id' => 3, 'academy_position' => 3, 'public_points' => 0],   // academy 100, public rank 3 → 75  = 175
    ]);

    $scores = attributedCalculator()->rank($tallies, 60, 40);

    expect($scores)->toHaveCount(3)
        ->and($scores[0]->nomineeId)->toBe(1)   // tie 300 → higher academy attributed
        ->and($scores[1]->nomineeId)->toBe(2)
        ->and($scores[2]->nomineeId)->toBe(3);
});

it('still attributes a public score when there are no public votes', function (): void {
    // No public points → the public ranking falls back to the academy order (position asc).
    $tallies = attributedTallies([
        ['id' => 1, 'academy_position' => 1, 'public_points' => 0],   // academy 200, public rank 1 → 150 = 350
        ['id' => 2, 'academy_position' => 2, 'public_points' => 0],   // academy 150, public rank 2 → 100 = 250
        ['id' => 3, 'academy_position' => 3, 'public_points' => 0],   // academy 100, public rank 3 → 75  = 175
    ]);

    $scores = attributedCalculator()->rank($tallies, 60, 40);

    expect($scores[0]->nomineeId)->toBe(1)
        ->and($scores[0]->finalScore)->toBe(350.0)
        ->and($scores[1]->nomineeId)->toBe(2)
        ->and($scores[2]->nomineeId)->toBe(3);
});
