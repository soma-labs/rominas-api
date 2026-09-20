<?php

declare(strict_types=1);

namespace Rominas\Scoring\DataTransferObjects;

use Rominas\Catalog\Enums\NomineeType;

/**
 * One nominee's computed standing within a category: the raw summed points on each side, two normalized
 * class values, a combined `finalScore`, and the resulting `position` (1 = winner).
 *
 * The three normalized fields are algorithm-dependent (see `config('scoring.algorithm')`):
 * - `share` ({@see \Rominas\Scoring\Support\ScoreCalculator}) — `academyShare` / `publicShare` are the
 *   0..1 class shares and `finalScore` is the 0..1 weighted blend.
 * - `attributed` ({@see \Rominas\Scoring\Support\AttributedScoreCalculator}) — `academyShare` /
 *   `publicShare` carry the discrete academy / public attributed scores and `finalScore` their total
 *   (whole points, NOT 0..1).
 *
 * `academyPoints` / `publicPoints` are always the raw summed points. Values are rounded for display only.
 */
final class NomineeScore
{
    public function __construct(
        public readonly NomineeType $nomineeType,
        public readonly int $nomineeId,
        public readonly int $academyPoints,
        public readonly int $publicPoints,
        public readonly float $academyShare,
        public readonly float $publicShare,
        public readonly float $finalScore,
        public readonly int $position,
    ) {}
}
