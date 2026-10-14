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
     * @param  SpecialVote|null  $proposalVote  the vote the member's proposal carries, if they have one
     * @param  SpecialVote|null  $chosenVote  the vote the member chose for another member's entry, if any
     * @param  SpecialEntry|null  $proposal  the member's latest proposal in the category, whatever became of it
     * @param  Collection<int, SpecialEntry>  $entries  the approved entries, alphabetically
     */
    public function __construct(
        public readonly Category $category,
        public readonly ?SpecialVote $proposalVote,
        public readonly ?SpecialVote $chosenVote,
        public readonly ?SpecialEntry $proposal,
        public readonly Collection $entries,
    ) {}
}
