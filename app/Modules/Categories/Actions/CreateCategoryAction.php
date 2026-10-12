<?php

declare(strict_types=1);

namespace Rominas\Categories\Actions;

use Rominas\Categories\DataTransferObjects\CategoryData;
use Rominas\Categories\Model\Category;

class CreateCategoryAction
{
    public function execute(CategoryData $data): Category
    {
        return Category::create([
            'edition_id' => $data->edition_id,
            'name' => $data->name,
            'kind' => $data->kind,
            'nominee_type' => $data->nominee_type,
            'position' => $data->position,
            'description' => $data->description,
        ]);
    }
}
