<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Support;

use Illuminate\Validation\ValidationException;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;

/**
 * The guard shared by every admin change to a special category's entries. Reviewing may go on after
 * nominations close, but not once the edition's results are published: from then on the results are a
 * frozen snapshot and an entry changed afterwards would silently disagree with it.
 */
class SpecialAwardsReviewGuard
{
    /**
     * @throws ValidationException
     */
    public static function assertEditable(Category $category): void
    {
        $status = $category->edition->status;

        if ($status === EditionStatus::ResultsPublished || $status === EditionStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => __('The results of this edition are published, so its special entries can no longer be changed.'),
            ]);
        }
    }
}
