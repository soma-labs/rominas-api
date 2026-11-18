<?php

declare(strict_types=1);

namespace Rominas\Categories\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Categories\DataTransferObjects\CategoryData;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;

class UpdateCategoryAction
{
    /**
     * The shortlist length can change only until voting opens: the shortlist is frozen from then on.
     */
    private const array SHORTLIST_LENGTH_EDITABLE_STATUSES = [
        EditionStatus::Draft,
        EditionStatus::NominationsOpen,
        EditionStatus::NominationsClosed,
    ];

    /**
     * @throws ValidationException
     */
    public function execute(Category $category, CategoryData $data): Category
    {
        if ($data->shortlist_length !== $category->shortlist_length) {
            $this->assertShortlistLengthEditable($category);
        }

        $category->name = $data->name;
        $category->nominee_type = $data->nominee_type;
        $category->position = $data->position;
        $category->description = $data->description;
        $category->placeholder = $data->placeholder;
        $category->eligibility_criteria = $data->eligibility_criteria;
        $category->publicly_visible = $data->publicly_visible;
        $category->shortlist_length = $data->shortlist_length;

        $category->save();

        return $category;
    }

    /**
     * @throws ValidationException
     */
    private function assertShortlistLengthEditable(Category $category): void
    {
        if (! in_array($category->edition->status, self::SHORTLIST_LENGTH_EDITABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'shortlist_length' => __('The shortlist length can no longer be changed once voting has opened.'),
            ]);
        }
    }
}
