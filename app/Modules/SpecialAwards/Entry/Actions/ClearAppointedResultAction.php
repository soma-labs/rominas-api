<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Support\SpecialAwardsReviewGuard;

/**
 * Removes the result entered for an appointed category, leaving it without a winner.
 */
class ClearAppointedResultAction
{
    public function execute(Category $category): void
    {
        if ($category->kind !== CategoryKind::Appointed) {
            throw ValidationException::withMessages([
                'category' => __('Only an appointed category takes a result entered by hand.'),
            ]);
        }

        SpecialAwardsReviewGuard::assertEditable($category);

        SpecialEntry::query()->forCategory($category)->delete();
    }
}
