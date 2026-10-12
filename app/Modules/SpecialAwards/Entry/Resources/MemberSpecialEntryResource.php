<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;

/**
 * An approved entry as a member sees it: its text only — not who proposed it, not how many voted for it.
 */
class MemberSpecialEntryResource extends JsonResource
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
            'title' => $entry->title,
            'description' => $entry->description,
        ];
    }
}
