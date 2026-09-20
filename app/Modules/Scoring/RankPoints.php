<?php

declare(strict_types=1);

namespace Rominas\Scoring;

use InvalidArgumentException;

/**
 * The academy points-per-rank curve (client PHAZE 1, 2026-09-18): a nominee ranked `r` in an academy
 * nomination earns `2 · (6 − r)` points, i.e. rank 1 → 10 pts, 2 → 8, 3 → 6, 4 → 4, 5 → 2. This is the
 * single source of truth for the academy curve; the nominee shortlist aggregation and the Scoring engine
 * read it here rather than inlining the formula.
 *
 * The curve is exactly twice the original 5/4/3/2/1: a uniform scale factor, so it leaves the shortlist
 * ordering and both scoring algorithms unchanged (the share algorithm cancels the factor; the attributed
 * ladder keys off rank position, not raw points). It only affects the raw academy point values on display.
 *
 * Public ballots use their own, shorter curve — see {@see PublicRankPoints}.
 */
final class RankPoints
{
    /** The number of ranked slots per category (top-N). */
    public const int RANKS = 5;

    public static function forRank(int $rank): int
    {
        if ($rank < 1 || $rank > self::RANKS) {
            throw new InvalidArgumentException("Rank must be between 1 and " . self::RANKS . ", got {$rank}.");
        }

        return 2 * (self::RANKS + 1 - $rank);
    }
}
