<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Rominas\Academy\Shortlist\Actions\AdjustCategoryShortlistAction;
use Rominas\Academy\Shortlist\Actions\CountRejectedPicksAction;
use Rominas\Academy\Shortlist\Actions\GenerateCategoryShortlistAction;
use Rominas\Academy\Shortlist\Actions\GenerateEditionShortlistsAction;
use Rominas\Academy\Shortlist\Actions\GetCategoryShortlistCandidatesAction;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Academy\Shortlist\Requests\AdjustShortlistRequest;
use Rominas\Academy\Shortlist\Resources\ShortlistEntryResource;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

/**
 * Admin-facing nominee shortlist: review an edition's shortlist and generate it on demand (per category
 * or in bulk) once nominations close. Authorization is the `shortlists` permission via ShortlistEntryPolicy.
 */
class ShortlistController
{
    public function index(Edition $edition, Request $request, CountRejectedPicksAction $rejectedPicks): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return ShortlistEntryResource::collection(
            ShortlistEntry::query()
                ->forEdition($edition)
                ->visibleToUser($user)
                ->with('nominee')
                ->orderBy('category_id')
                ->orderBy('position')
                ->get(),
        )->additional(['meta' => ['rejected_picks' => $rejectedPicks->execute($edition)]]);
    }

    public function generateEdition(Edition $edition, GenerateEditionShortlistsAction $action): AnonymousResourceCollection
    {
        return ShortlistEntryResource::collection($action->execute($edition));
    }

    public function generateCategory(
        Edition $edition,
        Category $category,
        GenerateCategoryShortlistAction $action,
    ): AnonymousResourceCollection {
        return ShortlistEntryResource::collection($action->execute($edition, $category));
    }

    /**
     * The ranked candidate pool for a category — every academy-nominated nominee, ordered by points —
     * for the manual review UI to reorder/trim.
     */
    public function candidates(
        Edition $edition,
        Category $category,
        GetCategoryShortlistCandidatesAction $action,
    ): JsonResponse {
        return response()->json(['data' => $action->execute($edition, $category)]);
    }

    /**
     * Replace a category's shortlist with the admin's final ordered nominees.
     */
    public function adjust(
        Edition $edition,
        Category $category,
        AdjustShortlistRequest $request,
        AdjustCategoryShortlistAction $action,
    ): AnonymousResourceCollection {
        return ShortlistEntryResource::collection(
            $action->execute($edition, $category, $request->nomineeIds()),
        );
    }
}
