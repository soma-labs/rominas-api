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
            status: MemberStatus::AwaitingInvitation,
        );
    }

    public static function fromUpdateRequest(UpdateMemberRequest $request, Member $member): MemberData
    {
        $validated = $request->validated();

        return new MemberData(
            name: $validated['name'],
            email: $validated['email'],
            status: isset($validated['status'])
                ? MemberStatus::from($validated['status'])
                : $member->status,
        );
    }
}
