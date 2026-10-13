<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;

/**
 * A special-category entry as the reviewing admin sees it. Deliberately carries no vote count: how the
 * members voted is part of the results, which stay custodian-only until they are published.
 */
class SpecialEntryResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var SpecialEntry $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'category_id' => $entry->category_id,
            'title' => $entry->title,
            'status' => $entry->status->value,
            'status_label' => $entry->status->label(),
            'proposed_by' => $this->whenLoaded('proposedBy', fn(): ?array => $entry->proposedBy === null ? null : [
                'id' => $entry->proposedBy->id,
                'name' => $entry->proposedBy->name,
            ]),
            'merged_into_entry_id' => $entry->merged_into_entry_id,
            'review_note' => $entry->review_note,
            'reviewed_at' => $entry->reviewed_at,
            'similar' => $this->when($entry->similar !== null, $entry->similar),
            'created_at' => $entry->created_at,
            'updated_at' => $entry->updated_at,
        ];
    }
}
