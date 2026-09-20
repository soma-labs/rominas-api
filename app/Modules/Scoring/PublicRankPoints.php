<?php

declare(strict_types=1);

namespace Rominas\Scoring;

use InvalidArgumentException;

/**
 * The public points-per-rank curve (client PHAZE 4, 2026-09-18): the public votes for only 3 of a
 * category's 5 shortlisted nominees, in order of preference, earning rank 1 → 10 pts, 2 → 8, 3 → 6.
 * Distinct from the academy {@see RankPoints} curve (which ranks all 5). Single source of truth for the
 * public curve; the Voting submission and the Scoring engine read it here.
 */
final class PublicRankPoints
{
    /** The number of ranked public picks per category. */
    public const int RANKS = 3;

    /** Points by rank: 1 → 10, 2 → 8, 3 → 6. */
    private const array POINTS = [1 => 10, 2 => 8, 3 => 6];

    public static function forRank(int $rank): int
    {
        if (! isset(self::POINTS[$rank])) {
            throw new InvalidArgumentException("Rank must be between 1 and " . self::RANKS . ", got {$rank}.");
        }

        return self::POINTS[$rank];
    }
}
