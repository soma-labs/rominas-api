<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Academy\Member\Model\Member;

class MemberResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var Member $member */
        $member = $this->resource;

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'status' => $member->status->value,
            'status_label' => $member->status->label(),
            'is_founding_member' => $member->is_founding_member,
            'email_verified_at' => $member->email_verified_at,
            'invited_at' => $member->invited_at,
            'activated_at' => $member->activated_at,
            'created_at' => $member->created_at,
            'updated_at' => $member->updated_at,
        ];
    }
}
