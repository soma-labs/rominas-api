<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Support\SpecialAwardsReviewGuard;
use Rominas\Users\Model\User;

/**
 * Approves a pending proposal: the entry joins the pool other members vote from, keeping its proposer's
 * vote. Refused when an approved entry with the same normalized title already exists in the category —
 * that is a duplicate, to be merged instead ({@see MergeSpecialEntryAction}).
 */
class ApproveSpecialEntryAction
{
    public function execute(SpecialEntry $entry, User $reviewer): SpecialEntry
    {
        SpecialAwardsReviewGuard::assertEditable($entry->category);

        if ($entry->status !== SpecialEntryStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('Only a pending entry can be approved.'),
            ]);
        }

        $duplicateExists = SpecialEntry::query()
            ->forCategory($entry->category)
            ->approved()
            ->where('normalized_title', '=', $entry->normalized_title)
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'title' => __('An approved entry with this title already exists. Merge this one into it instead.'),
            ]);
        }

        $entry->forceFill([
            'status' => SpecialEntryStatus::Approved,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        return $entry;
    }
}
