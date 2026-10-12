<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Support\SpecialAwardsReviewGuard;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;
use Rominas\Users\Model\User;

/**
 * Folds a duplicate entry into the entry that stays: every vote on the duplicate (its proposer's
 * included) moves to the target, and the duplicate is marked merged. This is how duplicates are handled
 * at review — nobody loses their say and the votes are not split between two spellings. Works for a
 * pending proposal and for an entry that was approved before the duplicate was noticed.
 *
 * A member holds one vote per category, so moving votes inside a category can never collide.
 */
class MergeSpecialEntryAction
{
    public function execute(SpecialEntry $entry, int $targetEntryId, User $reviewer): SpecialEntry
    {
        SpecialAwardsReviewGuard::assertEditable($entry->category);

        if ($entry->status !== SpecialEntryStatus::Pending && $entry->status !== SpecialEntryStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => __('Only a pending or approved entry can be merged.'),
            ]);
        }

        $target = SpecialEntry::query()
            ->forCategory($entry->category)
            ->approved()
            ->whereKey($targetEntryId)
            ->whereKeyNot($entry->id)
            ->first();

        if ($target === null) {
            throw ValidationException::withMessages([
                'target_entry_id' => __('An entry can only be merged into another approved entry of the same category.'),
            ]);
        }

        return DB::transaction(function () use ($entry, $target, $reviewer): SpecialEntry {
            SpecialVote::query()->forEntry($entry)->update(['special_entry_id' => $target->id]);

            $entry->forceFill([
                'status' => SpecialEntryStatus::Merged,
                'merged_into_entry_id' => $target->id,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            return $entry;
        });
    }
}
