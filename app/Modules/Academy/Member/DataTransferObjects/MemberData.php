<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\DataTransferObjects;

use Rominas\Academy\Member\Enums\MemberStatus;

class MemberData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $position = null,
        public ?string $company = null,
        public ?string $phone = null,
        public MemberStatus $status = MemberStatus::AwaitingInvitation,
    ) {}
}
