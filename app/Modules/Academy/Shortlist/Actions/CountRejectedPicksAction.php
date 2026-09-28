<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Actions;

use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Nomination\QueryBuilders\NominationQueryBuilder;
use Rominas\Catalog\NomineeSubmission\QueryBuilders\NomineeSubmissionQueryBuilder;
use Rominas\Editions\Model\Edition;

/**
 * Counts, per category, submitted academy rankings whose free-text pick was rejected during
 * reconciliation — the same rankings {@see GenerateCategoryShortlistAction::tallyPoints()} silently
 * excludes from the tally (via `NominationRankingQueryBuilder::resolved()`). Surfaced to the admin
 * shortlist view so a rejected pick doesn't just vanish from the count.
 */
class CountRejectedPicksAction
{
    /**
     * @return array<int, int> category_id => rejected pick count
     */
    public function execute(Edition $edition): array
    {
        return NominationRanking::query()
            ->whereHas('nomination', fn(NominationQueryBuilder $query) => $query->forEdition($edition)->submitted())
            ->unresolved()
            ->whereHas('nomineeSubmission', fn(NomineeSubmissionQueryBuilder $query) => $query->rejected())
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id')
            ->map(static fn(mixed $count): int => (int) $count)
            ->all();
    }
}
