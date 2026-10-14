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
 * vote for another member's entry is untouched. Only a pending entry can be taken back, and it disappears
 * with their vote. Once approved (or merged into an approved one) the proposal stands.
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

        // A merged proposal's vote sits on the approved entry it was merged into.
        if ($vote->entry->status !== SpecialEntryStatus::Pending) {
            throw ValidationException::withMessages([
                'category' => __('An approved proposal can no longer be withdrawn.'),
            ]);
        }

        DB::transaction(function () use ($vote): void {
            $vote->delete();
            $vote->entry->delete();
        });
    }
}
