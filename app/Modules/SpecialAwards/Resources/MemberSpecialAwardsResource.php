<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\SpecialAwards\DataTransferObjects\MemberSpecialAwardsData;
use Rominas\SpecialAwards\DataTransferObjects\MemberSpecialCategoryData;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Entry\Resources\MemberSpecialEntryResource;

/**
 * A member's view of the edition's special awards. Per category: `action` is what the member did
 * (`proposed`, `voted` or null), `entry_id` the entry their vote is on, `proposal` their own latest
 * proposal with its review outcome, and `entries` the approved entries they may vote for. Carries the
 * nomination window so the SPA can gate the form on it, as the ballot does. No vote counts, no other
 * member's pending proposal, no proposer names.
 */
class MemberSpecialAwardsResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var MemberSpecialAwardsData $awards */
        $awards = $this->resource;

        return [
            'edition_id' => $awards->edition->id,
            'nominations_start_at' => $awards->edition->nominations_start_at,
            'nominations_end_at' => $awards->edition->nominations_end_at,
            'categories' => array_map(
                fn(MemberSpecialCategoryData $category): array => $this->category($category),
                $awards->categories,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function category(MemberSpecialCategoryData $data): array
    {
        $action = null;

        if ($data->vote !== null) {
            $action = $data->vote->isOnOwnProposal() ? 'proposed' : 'voted';
        }

        return [
            'id' => $data->category->id,
            'name' => $data->category->name,
            'description' => $data->category->description,
            'action' => $action,
            'entry_id' => $data->vote?->special_entry_id,
            'proposal' => $data->proposal === null ? null : $this->proposal($data->proposal),
            'entries' => MemberSpecialEntryResource::collection($data->entries),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proposal(SpecialEntry $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->label(),
            'review_note' => $proposal->review_note,
            'merged_into' => $proposal->mergedInto === null ? null : [
                'id' => $proposal->mergedInto->id,
                'title' => $proposal->mergedInto->title,
            ],
        ];
    }
}
