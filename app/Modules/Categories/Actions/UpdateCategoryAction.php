<?php

declare(strict_types=1);

namespace Rominas\Categories\Actions;

use Rominas\Categories\DataTransferObjects\CategoryData;
use Rominas\Categories\Model\Category;

class UpdateCategoryAction
{
    public function execute(Category $category, CategoryData $data): Category
    {
        $category->name = $data->name;
        $category->nominee_type = $data->nominee_type;
        $category->position = $data->position;
        $category->description = $data->description;
        $category->placeholder = $data->placeholder;
        $category->eligibility_criteria = $data->eligibility_criteria;

        $category->save();

        return $category;
    }
}
