<?php

declare(strict_types=1);

namespace Rominas\Scoring\DataTransferObjects;

use Rominas\Scoring\Enums\ScoringAlgorithmType;

/**
 * An edition's complete computed results — one {@see CategoryScore} per category, in category order.
 * This is the value the (future) Results module persists, custodian-gates and exports; Scoring only
 * computes it, on demand.
 */
final class EditionScore
{
    /**
     * @param  list<CategoryScore>  $categories
     */
    public function __construct(
        public readonly int $editionId,
        public readonly array $categories,
        public readonly ScoringAlgorithmType $algorithm,
    ) {}
}
