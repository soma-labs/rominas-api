<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Vote\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Actions\ResolveOpenSpecialCategoryAction;
use Rominas\SpecialAwards\Entry\Actions\WithdrawSpecialEntryAction;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

/**
 * A member takes back the vote they chose in a category, which frees them to vote again. The vote a
 * proposal carries is not withdrawn here but with the proposal ({@see WithdrawSpecialEntryAction}).
 */
class WithdrawSpecialVoteAction
{
    public function __construct(
        private readonly ResolveOpenSpecialCategoryAction $resolveOpenCategory,
    ) {}

    public function execute(Member $member, Category $category): void
    {
        $this->resolveOpenCategory->execute($category);

        $vote = SpecialVote::query()->forMember($member)->forCategory($category)->chosen()->first();

        if ($vote === null) {
            throw ValidationException::withMessages([
                'category' => __('You have no vote to withdraw in this category.'),
            ]);
        }

        $vote->delete();
    }
}
