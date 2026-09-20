<?php

declare(strict_types=1);

namespace Rominas\Scoring\Support;

use Rominas\Scoring\DataTransferObjects\NomineeScore;

/**
 * A category's scoring engine: given each nominee's summed academy and public points (plus their academy
 * shortlist position), it produces a ranked list of {@see NomineeScore}, best-first, with `position` 1..n.
 *
 * Two implementations exist, selected by `config('scoring.algorithm')`:
 * - {@see ScoreCalculator} — our per-category share × per-edition weight blend (`share`).
 * - {@see AttributedScoreCalculator} — the client's discrete rank→score ladders (`attributed`, default).
 *
 * How the {@see NomineeScore} share/score fields are populated is algorithm-dependent — see each class.
 */
interface ScoringAlgorithm
{
    /**
     * @param  list<array{nominee_type: \Rominas\Catalog\Enums\NomineeType, nominee_id: int, academy_points: int, public_points: int, academy_position: int}>  $tallies
     * @return list<NomineeScore>
     */
    public function rank(array $tallies, int $academyWeight, int $publicWeight): array;
}
