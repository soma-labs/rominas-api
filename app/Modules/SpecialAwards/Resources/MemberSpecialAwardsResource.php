<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Academy\Member\Model\Member;
use Rominas\SpecialAwards\DataTransferObjects\MemberSpecialAwardsData;
use Rominas\SpecialAwards\DataTransferObjects\MemberSpecialCategoryData;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Entry\Resources\MemberSpecialEntryResource;

/**
 * A member's view of the edition's special awards. Per category: `proposed_entry_id` is the entry the vote
 * their proposal carries is on (null = they may propose), `voted_entry_id` the entry they chose to vote
 * for (null = they may vote), `proposal` their own latest proposal with its review outcome, and `entries`
 * the approved entries, each marked `proposed_by_me` when it is the member's own (they cannot vote for
 * it). Carries the nomination window so the SPA can gate the form on it, as the ballot does. No vote
 * counts, no other member's pending proposal, no proposer names.
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
                fn(MemberSpecialCategoryData $category): array => $this->category($category, $awards->member),
                $awards->categories,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function category(MemberSpecialCategoryData $data, Member $member): array
    {
        return [
            'id' => $data->category->id,
            'name' => $data->category->name,
            'description' => $data->category->description,
            'placeholder' => $data->category->placeholder,
            'eligibility_criteria' => $data->category->eligibility_criteria,
            'proposed_entry_id' => $data->proposalVote?->special_entry_id,
            'voted_entry_id' => $data->chosenVote?->special_entry_id,
            'proposal' => $data->proposal === null ? null : $this->proposal($data->proposal),
            'entries' => $data->entries
                ->map(fn(SpecialEntry $entry): array => [
                    ...(new MemberSpecialEntryResource($entry))->resolve(),
                    'proposed_by_me' => $entry->proposed_by_member_id === $member->id,
                ])
                ->values()
                ->all(),
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
