<?php

declare(strict_types=1);

namespace Rominas\Voting\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Voting\DataTransferObjects\VotingStatusData;

/**
 * Public voting status: the state, and the active edition's name and voting window (all null when there
 * is no active edition). Deliberately exposes nothing else about the edition.
 */
class VotingStatusResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var VotingStatusData $status */
        $status = $this->resource;
        $edition = $status->edition;

        return [
            'state' => $status->state->value,
            'edition' => $edition === null ? null : ['name' => $edition->name],
            'voting_start_at' => $edition?->voting_start_at,
            'voting_end_at' => $edition?->voting_end_at,
        ];
    }
}
