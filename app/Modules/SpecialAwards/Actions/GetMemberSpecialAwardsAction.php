<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Actions;

use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\SpecialAwards\DataTransferObjects\MemberSpecialAwardsData;
use Rominas\SpecialAwards\DataTransferObjects\MemberSpecialCategoryData;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

/**
 * Builds what a member sees of an edition's special awards: each propose-or-vote category with the
 * approved entries they can vote for, the vote their proposal carries and the vote they chose (either,
 * both or neither), and what became of their own latest proposal. Appointed categories are never included, and neither are other
 * members' pending proposals or any vote count.
 */
class GetMemberSpecialAwardsAction
{
    public function execute(Member $member, Edition $edition): MemberSpecialAwardsData
    {
        $categories = Category::query()
            ->filterByEditionId($edition->id)
            ->ofKind(CategoryKind::ProposeOrVote)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $categoryIds = $categories->modelKeys();

        $votes = SpecialVote::query()
            ->forMember($member)
            ->whereIn('category_id', $categoryIds)
            ->get();

        $proposalVotes = $votes->where('via_proposal', '=', true)->keyBy('category_id');
        $chosenVotes = $votes->where('via_proposal', '=', false)->keyBy('category_id');

        // Latest first, so the first one met per category is the member's most recent proposal.
        $proposals = SpecialEntry::query()
            ->forProposer($member)
            ->whereIn('category_id', $categoryIds)
            ->with('mergedInto')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('category_id');

        $entries = SpecialEntry::query()
            ->approved()
            ->whereIn('category_id', $categoryIds)
            ->orderBy('title', 'asc')
            ->get()
            ->groupBy('category_id');

        $data = [];

        foreach ($categories as $category) {
            $data[] = new MemberSpecialCategoryData(
                category: $category,
                proposalVote: $proposalVotes->get($category->id),
                chosenVote: $chosenVotes->get($category->id),
                proposal: $proposals->get($category->id)?->first(),
                entries: $entries->get($category->id) ?? collect(),
            );
        }

        return new MemberSpecialAwardsData($edition, $member, $data);
    }
}
