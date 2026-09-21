<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Academy\Nomination\Model\NominationRanking;

class NominationRankingResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var NominationRanking $ranking */
        $ranking = $this->resource;

        /** @var object{id: int, name: string, slug: string}|null $nominee */
        $nominee = $ranking->nominee;
        $submission = $ranking->nomineeSubmission;

        return [
            'rank' => $ranking->rank,
            'nominee_type' => $ranking->nominee_type->value,
            // The name the member typed, and its reconciliation state.
            'raw_name' => $submission?->raw_name,
            'submission_status' => $submission?->status->value,
            // Populated once the typed name has been reconciled to a canonical Catalog entity.
            'nominee_id' => $ranking->nominee_id,
            'nominee' => $nominee === null ? null : [
                'id' => $nominee->id,
                'name' => $nominee->name,
                'slug' => $nominee->slug,
            ],
        ];
    }
}
