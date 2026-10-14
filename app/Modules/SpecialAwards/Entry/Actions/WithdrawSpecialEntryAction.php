<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Actions\ResolveOpenSpecialCategoryAction;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

/**
 * A member takes back the entry they proposed in a category, which frees them to propose again; their
 * vote for another member's entry is untouched. While the entry is pending it disappears with their vote.
 * Once approved it belongs to the pool other members vote from, so it stays and only the vote the
 * proposal carried is removed.
 */
class WithdrawSpecialEntryAction
{
    public function __construct(
        private readonly ResolveOpenSpecialCategoryAction $resolveOpenCategory,
    ) {}

    public function execute(Member $member, Category $category): void
    {
        $this->resolveOpenCategory->execute($category);

        $vote = SpecialVote::query()->forMember($member)->forCategory($category)->viaProposal()->with('entry')->first();

        if ($vote === null) {
            throw ValidationException::withMessages([
                'category' => __('You have no proposal to withdraw in this category.'),
            ]);
        }

        DB::transaction(function () use ($vote): void {
            $entry = $vote->entry;

            $vote->delete();

            if ($entry->status === SpecialEntryStatus::Pending) {
                $entry->delete();
            }
        });
    }
}
