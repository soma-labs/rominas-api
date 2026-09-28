<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Academy\MemberProposal\Enums\MemberProposalStatus;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Users\Model\User;

/**
 * Rejects a pending proposal, recording the reviewer and an optional note. No Member is created.
 */
class RejectMemberProposalAction
{
    public function execute(MemberProposal $proposal, User $reviewer, ?string $note = null): MemberProposal
    {
        if ($proposal->status !== MemberProposalStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('Only a pending proposal can be rejected.'),
            ]);
        }

        $proposal->forceFill([
            'status' => MemberProposalStatus::Rejected,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        return $proposal;
    }
}
