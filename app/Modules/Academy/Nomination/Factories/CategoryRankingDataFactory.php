<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Factories;

use Rominas\Academy\Nomination\DataTransferObjects\CategoryRankingData;
use Rominas\Academy\Nomination\DataTransferObjects\RankedNomineeData;
use Rominas\Academy\Nomination\Requests\SaveCategoryRankingRequest;
use Rominas\Categories\Model\Category;

class CategoryRankingDataFactory
{
    public static function fromRequest(SaveCategoryRankingRequest $request, Category $category): CategoryRankingData
    {
        /** @var list<array{name: string, nominee_id?: int|string|null}> $picks */
        $picks = array_values($request->validated()['nominees']);

        return new CategoryRankingData(
            categoryId: (int) $category->id,
            nominees: array_map(
                static fn(array $pick): RankedNomineeData => new RankedNomineeData(
                    name: trim($pick['name']),
                    nomineeId: isset($pick['nominee_id']) ? (int) $pick['nominee_id'] : null,
                ),
                $picks,
            ),
        );
    }
}
