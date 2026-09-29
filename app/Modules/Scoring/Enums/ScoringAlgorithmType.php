<?php

declare(strict_types=1);

namespace Rominas\Scoring\Enums;

/**
 * The scoring algorithms Scoring can run (config/scoring.php `algorithm`). Recorded on every computed
 * {@see \Rominas\Scoring\DataTransferObjects\EditionScore} and on the frozen snapshot, because it decides
 * what the share/final fields mean (whole ladder points vs. 0..1 fractions).
 */
enum ScoringAlgorithmType: string
{
    case Attributed = 'attributed';
    case Share = 'share';
}
