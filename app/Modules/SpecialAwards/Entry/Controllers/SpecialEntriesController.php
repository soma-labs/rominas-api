<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\SpecialAwards\Entry\Actions\ApproveSpecialEntryAction;
use Rominas\SpecialAwards\Entry\Actions\ClearAppointedResultAction;
use Rominas\SpecialAwards\Entry\Actions\MergeSpecialEntryAction;
use Rominas\SpecialAwards\Entry\Actions\RejectSpecialEntryAction;
use Rominas\SpecialAwards\Entry\Actions\SetAppointedResultAction;
use Rominas\SpecialAwards\Entry\Actions\SuggestSimilarSpecialEntriesAction;
use Rominas\SpecialAwards\Entry\Actions\UpdateSpecialEntryAction;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Factories\SpecialEntryDataFactory;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Entry\QueryBuilders\SpecialEntryQueryBuilder;
use Rominas\SpecialAwards\Entry\Requests\MergeSpecialEntryRequest;
use Rominas\SpecialAwards\Entry\Requests\RejectSpecialEntryRequest;
use Rominas\SpecialAwards\Entry\Requests\SaveSpecialEntryRequest;
use Rominas\SpecialAwards\Entry\Resources\SpecialEntryResource;
use Rominas\Users\Model\User;

use function abort_unless;
use function response;

/**
 * Admin side of an edition's special awards (guard `sanctum` + the `specialAwards` permission): review
 * the entries members propose — approve, reject, or merge a duplicate into the entry that stays — and
 * enter the result of an appointed category. Vote counts are not shown here; they belong to the results.
 */
class SpecialEntriesController
{
    public function index(Edition $edition, Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = SpecialEntry::query()->visibleToUser($user)->forEdition($edition)->with('proposedBy');

        if ($request->filled('status')) {
            $query = $query->filterByStatus(SpecialEntryStatus::from((string) $request->query('status')));
        }

        if ($request->filled('kind')) {
            $query = $query->forCategoryKind(CategoryKind::from((string) $request->query('kind')));
        }

        if ($request->filled('categoryId')) {
            $query = $query->where('category_id', '=', (int) $request->query('categoryId'));
        }

        return SpecialEntryResource::collection(
            $query
                ->search($request->query('search'))
                ->orderBy($request->query('orderBy', 'created_at'), $request->query('orderDir', 'desc'))
                ->paginate($request->query('perPage', SpecialEntryQueryBuilder::PER_PAGE)),
        );
    }

    public function show(
        Edition $edition,
        SpecialEntry $specialEntry,
        Request $request,
        SuggestSimilarSpecialEntriesAction $suggest,
    ): SpecialEntryResource {
        $this->assertInEdition($edition, $specialEntry);

        $specialEntry->load('proposedBy');

        if ($request->boolean('with_similar')) {
            $specialEntry->similar = $suggest->execute($specialEntry);
        }

        return new SpecialEntryResource($specialEntry);
    }

    public function update(
        Edition $edition,
        SpecialEntry $specialEntry,
        SaveSpecialEntryRequest $request,
        UpdateSpecialEntryAction $action,
    ): SpecialEntryResource {
        $this->assertInEdition($edition, $specialEntry);

        $entry = $action->execute($specialEntry, SpecialEntryDataFactory::fromRequest($request));

        return new SpecialEntryResource($entry->load('proposedBy'));
    }

    public function approve(
        Edition $edition,
        SpecialEntry $specialEntry,
        Request $request,
        ApproveSpecialEntryAction $action,
    ): SpecialEntryResource {
        $this->assertInEdition($edition, $specialEntry);

        /** @var User $user */
        $user = $request->user();

        return new SpecialEntryResource($action->execute($specialEntry, $user)->load('proposedBy'));
    }

    public function reject(
        Edition $edition,
        SpecialEntry $specialEntry,
        RejectSpecialEntryRequest $request,
        RejectSpecialEntryAction $action,
    ): SpecialEntryResource {
        $this->assertInEdition($edition, $specialEntry);

        /** @var User $user */
        $user = $request->user();

        $entry = $action->execute($specialEntry, $user, (string) $request->validated()['note']);

        return new SpecialEntryResource($entry->load('proposedBy'));
    }

    public function merge(
        Edition $edition,
        SpecialEntry $specialEntry,
        MergeSpecialEntryRequest $request,
        MergeSpecialEntryAction $action,
    ): SpecialEntryResource {
        $this->assertInEdition($edition, $specialEntry);

        /** @var User $user */
        $user = $request->user();

        $entry = $action->execute($specialEntry, $request->targetEntryId(), $user);

        return new SpecialEntryResource($entry->load('proposedBy'));
    }

    /**
     * Enter or replace the result of an appointed category.
     */
    public function setAppointedResult(
        Edition $edition,
        Category $category,
        SaveSpecialEntryRequest $request,
        SetAppointedResultAction $action,
    ): SpecialEntryResource {
        abort_unless($category->edition_id === $edition->id, 404);

        /** @var User $user */
        $user = $request->user();

        return new SpecialEntryResource(
            $action->execute($category, SpecialEntryDataFactory::fromRequest($request), $user),
        );
    }

    public function clearAppointedResult(
        Edition $edition,
        Category $category,
        ClearAppointedResultAction $action,
    ): JsonResponse {
        abort_unless($category->edition_id === $edition->id, 404);

        $action->execute($category);

        return response()->json(['success' => true]);
    }

    private function assertInEdition(Edition $edition, SpecialEntry $entry): void
    {
        abort_unless($entry->category->edition_id === $edition->id, 404);
    }
}
