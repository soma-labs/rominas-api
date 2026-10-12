<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Entry\DataTransferObjects\SpecialEntryData;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Support\SpecialAwardsReviewGuard;
use Rominas\Users\Model\User;

/**
 * Enters (or replaces) the result of an appointed category — the winner a third party decided, typed in
 * by an admin. An appointed category holds at most one entry: approved, with no proposer.
 */
class SetAppointedResultAction
{
    public function execute(Category $category, SpecialEntryData $data, User $user): SpecialEntry
    {
        if ($category->kind !== CategoryKind::Appointed) {
            throw ValidationException::withMessages([
                'category' => __('Only an appointed category takes a result entered by hand.'),
            ]);
        }

        SpecialAwardsReviewGuard::assertEditable($category);

        return DB::transaction(function () use ($category, $data, $user): SpecialEntry {
            $entry = SpecialEntry::query()->forCategory($category)->first()
                ?? new SpecialEntry(['category_id' => $category->id]);

            $entry->forceFill([
                'title' => $data->title,
                'description' => $data->description,
                'status' => SpecialEntryStatus::Approved,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
            ])->save();

            return $entry;
        });
    }
}
