<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Model\MemberProposal;

/**
 * Fired after a member proposes a future academy member (see CreateMemberProposalAction). Listeners
 * are wired explicitly in EventServiceProvider (auto-discovery does not scan app/Modules).
 */
readonly class MemberProposalCreated
{
    use Dispatchable;

    public function __construct(
        public MemberProposal $proposal,
        public Member $proposer,
    ) {}
}
