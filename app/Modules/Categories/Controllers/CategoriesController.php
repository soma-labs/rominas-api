<?php

declare(strict_types=1);

namespace Rominas\Categories\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Rominas\Categories\Actions\CreateCategoryAction;
use Rominas\Categories\Actions\DeleteCategoryAction;
use Rominas\Categories\Actions\UpdateCategoryAction;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Factories\CategoryDataFactory;
use Rominas\Categories\Model\Category;
use Rominas\Categories\QueryBuilders\CategoryQueryBuilder;
use Rominas\Categories\Requests\CreateCategoryRequest;
use Rominas\Categories\Requests\UpdateCategoryRequest;
use Rominas\Categories\Resources\CategoryResource;
use Rominas\Users\Model\User;

use function response;

class CategoriesController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = Category::query()->visibleToUser($user);

        if ($request->has('editionId')) {
            $query = $query->filterByEditionId((int) $request->query('editionId'));
        }

        $kind = $request->enum('kind', CategoryKind::class);

        if ($kind !== null) {
            $query = $query->ofKind($kind);
        }

        return CategoryResource::collection(
            $query
                ->search($request->query('search'))
                ->orderBy(
                    $request->query('orderBy', 'position'),
                    $request->query('orderDir', 'asc'),
                )
                ->paginate($request->query('perPage', CategoryQueryBuilder::PER_PAGE)),
        );
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function create(CreateCategoryRequest $request, CreateCategoryAction $action): JsonResponse
    {
        $category = $action->execute(CategoryDataFactory::fromCreateRequest($request));

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(
        Category $category,
        UpdateCategoryRequest $request,
        UpdateCategoryAction $action,
    ): CategoryResource {
        $category = $action->execute($category, CategoryDataFactory::fromUpdateRequest($request, $category));

        return new CategoryResource($category);
    }

    public function delete(Category $category, DeleteCategoryAction $action): JsonResponse
    {
        $result = $action->execute($category);

        return response()->json(['success' => $result]);
    }
}
