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
            'has_started_nominations' => $this->resource->getAttribute('has_started_nominations') === null
                ? null
                : (bool) $this->resource->getAttribute('has_started_nominations'),

            'has_submitted_nominations' => $this->resource->getAttribute('has_submitted_nominations') === null
                ? null
                : (bool) $this->resource->getAttribute('has_submitted_nominations'),

            'has_submitted_member_proposals' => $this->resource->getAttribute('has_submitted_member_proposals') === null
                ? null
                : (bool) $this->resource->getAttribute('has_submitted_member_proposals'),
        ];
    }
}
