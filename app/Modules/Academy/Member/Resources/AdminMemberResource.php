<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use JsonSerializable;
use Rominas\Academy\Member\Model\Member;

/**
 * The admin's view of a member: {@see MemberResource} plus the contact details, which the
 * member-facing endpoints never return.
 */
class AdminMemberResource extends MemberResource
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
            ...parent::toArray($request),
            'position' => $member->position,
            'company' => $member->company,
            'phone' => $member->phone,
        ];
    }
}
