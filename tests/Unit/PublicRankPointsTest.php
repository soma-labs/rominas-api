<?php

declare(strict_types=1);

use Rominas\Scoring\PublicRankPoints;

it('awards the public curve points for each valid rank', function (int $rank, int $points): void {
    expect(PublicRankPoints::forRank($rank))->toBe($points);
})->with([
    'rank 1' => [1, 10],
    'rank 2' => [2, 8],
    'rank 3' => [3, 6],
]);

it('rejects a rank outside 1..3', function (int $rank): void {
    PublicRankPoints::forRank($rank);
})->with([0, 4, 5, -1])->throws(InvalidArgumentException::class);
