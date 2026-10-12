<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\SpecialAwards\Entry\DataTransferObjects\SpecialEntryData;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Support\SpecialAwardsReviewGuard;

/**
 * An admin corrects the text of a pending or approved entry (a typo, a clearer description). The votes
 * stay where they are.
 */
class UpdateSpecialEntryAction
{
    public function execute(SpecialEntry $entry, SpecialEntryData $data): SpecialEntry
    {
        SpecialAwardsReviewGuard::assertEditable($entry->category);

        if ($entry->status !== SpecialEntryStatus::Pending && $entry->status !== SpecialEntryStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => __('Only a pending or approved entry can be edited.'),
            ]);
        }

        $entry->title = $data->title;
        $entry->description = $data->description;
        $entry->save();

        return $entry;
    }
}
