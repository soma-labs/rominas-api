<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Actions;

use Rominas\Academy\Member\DataTransferObjects\MemberData;
use Rominas\Academy\Member\Model\Member;

class CreateMemberAction
{
    public function execute(MemberData $data): Member
    {
        return Member::create([
            'name' => $data->name,
            'email' => $data->email,
            'position' => $data->position,
            'company' => $data->company,
            'phone' => $data->phone,
            'status' => $data->status,
            'is_founding_member' => $data->isFoundingMember,
        ]);
    }
}
