<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Factories;

use Rominas\Academy\Member\DataTransferObjects\MemberData;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Member\Requests\CreateMemberRequest;
use Rominas\Academy\Member\Requests\UpdateMemberRequest;

class MemberDataFactory
{
    public static function fromCreateRequest(CreateMemberRequest $request): MemberData
    {
        $validated = $request->validated();

        return new MemberData(
            name: $validated['name'],
            email: $validated['email'],
            position: $validated['position'] ?? null,
            company: $validated['company'] ?? null,
            phone: $validated['phone'] ?? null,
            status: MemberStatus::AwaitingInvitation,
            isFoundingMember: (bool) ($validated['is_founding_member'] ?? false),
        );
    }

    public static function fromUpdateRequest(UpdateMemberRequest $request, Member $member): MemberData
    {
        $validated = $request->validated();

        return new MemberData(
            name: $validated['name'],
            email: $validated['email'],
            // A contact field left out of the request keeps its value; one sent as null is cleared.
            position: array_key_exists('position', $validated) ? $validated['position'] : $member->position,
            company: array_key_exists('company', $validated) ? $validated['company'] : $member->company,
            phone: array_key_exists('phone', $validated) ? $validated['phone'] : $member->phone,
            status: isset($validated['status'])
                ? MemberStatus::from($validated['status'])
                : $member->status,
            isFoundingMember: array_key_exists('is_founding_member', $validated)
                ? (bool) $validated['is_founding_member']
                : $member->is_founding_member,
        );
    }
}
