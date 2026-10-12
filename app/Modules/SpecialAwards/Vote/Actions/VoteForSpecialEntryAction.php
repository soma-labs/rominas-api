<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Vote\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Actions\ResolveOpenSpecialCategoryAction;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

/**
 * Casts a member's vote for an approved entry of a propose-or-vote category. A member who already voted
 * for another entry moves their vote; a member who proposed an entry cannot vote until they withdraw it.
 */
class VoteForSpecialEntryAction
{
    public function __construct(
        private readonly ResolveOpenSpecialCategoryAction $resolveOpenCategory,
    ) {}

    public function execute(Member $member, Category $category, int $entryId): SpecialVote
    {
        $this->resolveOpenCategory->execute($category);

        $entry = SpecialEntry::query()->forCategory($category)->approved()->whereKey($entryId)->first();

        if ($entry === null) {
            throw ValidationException::withMessages([
                'entry_id' => __('This entry cannot be voted for. Please choose again.'),
            ]);
        }

        $vote = SpecialVote::query()->forMember($member)->forCategory($category)->with('entry')->first();

        if ($vote !== null && $vote->isOnOwnProposal()) {
            throw ValidationException::withMessages([
                'category' => __('You have proposed an entry in this category, so you cannot vote in it.'),
            ]);
        }

        if ($vote !== null) {
            $vote->special_entry_id = $entry->id;
            $vote->save();

            return $vote;
        }

        try {
            return SpecialVote::create([
                'category_id' => $category->id,
                'member_id' => $member->id,
                'special_entry_id' => $entry->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests from the same member at once: the unique key let only one through.
            throw ValidationException::withMessages([
                'category' => __('You have already proposed or voted in this category.'),
            ]);
        }
    }
}
