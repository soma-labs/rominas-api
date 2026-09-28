<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Support;

use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Nomination\QueryBuilders\NominationQueryBuilder;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\Scoring\RankPoints;

/**
 * Sums each nominee's academy points for a category from the submitted nominations, via the
 * {@see RankPoints} curve. A category has a single nominee type, so nominees are keyed by id alone.
 * Shared by the shortlist candidates view and the manual adjust flow.
 */
class AcademyPointTally
{
    /**
     * @return array<int, int>  nominee id → summed academy points
     */
    public function perNominee(Edition $edition, Category $category): array
    {
        $rankings = NominationRanking::query()
            ->whereHas('nomination', fn(NominationQueryBuilder $query) => $query->forEdition($edition)->submitted())
            ->where('category_id', '=', $category->id)
            ->resolved()
            ->get(['nominee_id', 'rank']);

        $totals = [];

        foreach ($rankings as $ranking) {
            $id = (int) $ranking->nominee_id;
            $totals[$id] = ($totals[$id] ?? 0) + RankPoints::forRank($ranking->rank);
        }

        return $totals;
    }
}
