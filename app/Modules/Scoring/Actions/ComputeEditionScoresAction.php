<?php

declare(strict_types=1);

namespace Rominas\Scoring\Actions;

use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\Scoring\DataTransferObjects\EditionScore;
use Rominas\Scoring\Support\ScoringAlgorithm;

/**
 * Computes an entire edition's results — every ranked category, in order — on demand.
 *
 * Nothing is cached: the points are summed in SQL ({@see ComputeCategoryScoresAction}), so each call is
 * cheap, and every change after voting closes (a cancelled vote, a category hidden from the public) shows
 * at once. Nothing is persisted either — the Results module owns the custodian-gated view/export and the
 * snapshot frozen when results are published.
 */
class ComputeEditionScoresAction
{
    public function __construct(
        private readonly ComputeCategoryScoresAction $computeCategory,
        private readonly ScoringAlgorithm $algorithm,
    ) {}

    public function execute(Edition $edition): EditionScore
    {
        $this->computeCategory->assertScorable($edition);

        $categories = Category::query()
            ->filterByEditionId($edition->id)
            ->ranked()
            ->get()
            ->sortBy('position')
            ->values();

        $scores = [];

        foreach ($categories as $category) {
            /** @var Category $category */
            $scores[] = $this->computeCategory->execute($edition, $category);
        }

        return new EditionScore($edition->id, $scores, $this->algorithm->type());
    }
}
