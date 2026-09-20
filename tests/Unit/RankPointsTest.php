<?php

declare(strict_types=1);

use Rominas\Scoring\RankPoints;

it('awards the academy curve points for each valid rank', function (int $rank, int $points): void {
    expect(RankPoints::forRank($rank))->toBe($points);
})->with([
    'rank 1' => [1, 10],
    'rank 2' => [2, 8],
    'rank 3' => [3, 6],
    'rank 4' => [4, 4],
    'rank 5' => [5, 2],
]);

it('rejects a rank outside 1..5', function (int $rank): void {
    RankPoints::forRank($rank);
})->with([0, 6, -1])->throws(InvalidArgumentException::class);
