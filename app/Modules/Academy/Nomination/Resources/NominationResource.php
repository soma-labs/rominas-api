<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use JsonSerializable;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Categories\Model\Category;

/**
 * The member's whole ballot: status + every ranked category of the edition with the member's current ranked
 * picks (empty where none), so the SPA can render progress and resume. `can_submit` says the ballot
 * holds at least one pick, which is all a submission needs. Also carries the edition's
 * `nominations_start_at` / `nominations_end_at` so the SPA can gate the form on the window itself,
 * without needing a rejected write to discover it's closed (see `ResolveOpenNominationEditionAction`
 * for the write-side gate this mirrors for display only). Expects the wrapped Nomination to have
 * `edition.categories` and `rankings.nominee` loaded.
 */
class NominationResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var Nomination $nomination */
        $nomination = $this->resource;

        /** @var Collection<int, Collection<int, NominationRanking>> $byCategory */
        $byCategory = $nomination->rankings->groupBy('category_id');

        /** @var Collection<int, Category> $categories */
        $categories = $nomination->edition->categories
            ->filter(static fn(Category $category): bool => $category->isRanked());

        $categoriesPayload = $categories->map(function (Category $category) use ($byCategory): array {
            $rankings = ($byCategory->get($category->id) ?? collect())->sortBy('rank')->values();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'placeholder' => $category->placeholder,
                'eligibility_criteria' => $category->eligibility_criteria,
                'nominee_type' => $category->rankedNomineeType()->value,
                'has_nominees' => $rankings->isNotEmpty(),
                'rankings' => NominationRankingResource::collection($rankings),
            ];
        })->values();

        return [
            'edition_id' => $nomination->edition_id,
            'nominations_start_at' => $nomination->edition->nominations_start_at,
            'nominations_end_at' => $nomination->edition->nominations_end_at,
            'status' => $nomination->status->value,
            'status_label' => $nomination->status->label(),
            'submitted_at' => $nomination->submitted_at,
            'can_submit' => $nomination->rankings->isNotEmpty(),
            'categories' => $categoriesPayload,
        ];
    }
}
