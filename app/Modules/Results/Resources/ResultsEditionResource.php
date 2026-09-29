<?php

declare(strict_types=1);

namespace Rominas\Results\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Editions\Model\Edition;

/**
 * One row of the custodian's "which edition's results?" picker — just enough to label the edition and say
 * whether its results are already published.
 */
class ResultsEditionResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var Edition $edition */
        $edition = $this->resource;

        return [
            'id' => $edition->id,
            'name' => $edition->name,
            'slug' => $edition->slug,
            'status' => $edition->status->value,
            'status_label' => $edition->status->label(),
            'published_at' => $edition->resultSnapshot?->published_at,
        ];
    }
}
