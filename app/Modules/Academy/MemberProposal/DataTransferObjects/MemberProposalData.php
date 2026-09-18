<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\DataTransferObjects;

class MemberProposalData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $position = null,
        public ?string $company = null,
        public ?string $phone = null,
        public ?string $reason = null,
    ) {}
}
