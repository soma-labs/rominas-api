<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Vote\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

/**
 * @extends Builder<SpecialVote>
 */
class SpecialVoteQueryBuilder extends Builder
{
    public function forMember(Member $member): self
    {
        return $this->where('member_id', '=', $member->id);
    }

    public function forCategory(Category $category): self
    {
        return $this->where('category_id', '=', $category->id);
    }

    public function forEntry(SpecialEntry $entry): self
    {
        return $this->where('special_entry_id', '=', $entry->id);
    }
}
