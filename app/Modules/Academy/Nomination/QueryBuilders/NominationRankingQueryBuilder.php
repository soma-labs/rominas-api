<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Academy\Nomination\Model\NominationRanking;

/**
 * @extends Builder<NominationRanking>
 */
class NominationRankingQueryBuilder extends Builder
{
    /**
     * Excludes rankings whose free-text pick was rejected (never linked to a Catalog entity) —
     * `nominee_id` stays permanently null for those. Every caller that tallies or reports on
     * rankings by nominee must use this, or a rejected pick collapses onto a phantom nominee id 0.
     */
    public function resolved(): self
    {
        return $this->whereNotNull($this->qualifyColumn('nominee_id'));
    }
}
