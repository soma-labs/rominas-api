<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Academy\MemberProposal\Model\MemberProposal;

class MemberProposalResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var MemberProposal $proposal */
        $proposal = $this->resource;

        return [
            'id' => $proposal->id,
            'name' => $proposal->name,
            'email' => $proposal->email,
            'position' => $proposal->position,
            'company' => $proposal->company,
            'phone' => $proposal->phone,
            'reason' => $proposal->reason,
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->label(),
            'member_id' => $proposal->member_id,
            'review_note' => $proposal->review_note,
            'reviewed_at' => $proposal->reviewed_at,
            'created_at' => $proposal->created_at,
            'updated_at' => $proposal->updated_at,
        ];
    }
}
