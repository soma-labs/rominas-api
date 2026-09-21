<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Factories;

use Rominas\Academy\Nomination\DataTransferObjects\CategoryRankingData;
use Rominas\Academy\Nomination\Requests\SaveCategoryRankingRequest;
use Rominas\Categories\Model\Category;

class CategoryRankingDataFactory
{
    public static function fromRequest(SaveCategoryRankingRequest $request, Category $category): CategoryRankingData
    {
        /** @var list<string> $names */
        $names = array_map(
            static fn(mixed $name): string => trim((string) $name),
            array_values($request->validated()['nominees']),
        );

        return new CategoryRankingData(
            categoryId: (int) $category->id,
            nomineeNames: $names,
        );
    }
}
