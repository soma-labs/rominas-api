<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Academy\MemberProposal\Enums\MemberProposalStatus;
use Rominas\Academy\MemberProposal\Model\MemberProposal;

/**
 * Withdraws (deletes) a proposer's own still-pending proposal.
 */
class WithdrawMemberProposalAction
{
    public function execute(MemberProposal $proposal): bool
    {
        if ($proposal->status !== MemberProposalStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('Only a pending proposal can be withdrawn.'),
            ]);
        }

        return (bool) $proposal->delete();
    }
}
