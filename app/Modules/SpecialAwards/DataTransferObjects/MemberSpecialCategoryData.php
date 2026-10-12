<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\DataTransferObjects;

use Illuminate\Support\Collection;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

/**
 * One propose-or-vote category as a member sees it.
 */
final class MemberSpecialCategoryData
{
    /**
     * @param  SpecialVote|null  $vote  the member's vote in the category (with `entry` loaded), if they acted
     * @param  SpecialEntry|null  $proposal  the member's latest proposal in the category, whatever became of it
     * @param  Collection<int, SpecialEntry>  $entries  the approved entries, alphabetically
     */
    public function __construct(
        public readonly Category $category,
        public readonly ?SpecialVote $vote,
        public readonly ?SpecialEntry $proposal,
        public readonly Collection $entries,
    ) {}
}
