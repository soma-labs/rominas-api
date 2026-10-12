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
 * Rejects a pending proposal with a note the proposer reads. The proposer's vote goes with it, so they
 * are free to propose something else or vote, as long as the window is still open.
 */
class RejectSpecialEntryAction
{
    public function execute(SpecialEntry $entry, User $reviewer, string $note): SpecialEntry
    {
        SpecialAwardsReviewGuard::assertEditable($entry->category);

        if ($entry->status !== SpecialEntryStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('Only a pending entry can be rejected.'),
            ]);
        }

        return DB::transaction(function () use ($entry, $reviewer, $note): SpecialEntry {
            SpecialVote::query()->forEntry($entry)->delete();

            $entry->forceFill([
                'status' => SpecialEntryStatus::Rejected,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $entry;
        });
    }
}
