<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\Actions\CreateMemberAction;
use Rominas\Academy\Member\DataTransferObjects\MemberData;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Enums\MemberProposalStatus;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Users\Model\User;

/**
 * Approves a pending proposal: creates a Member awaiting invitation from it (feeding the manual
 * invitation flow) and records the review. Rejects a non-pending proposal, or one whose email has meanwhile
 * become a Member.
 */
class ApproveMemberProposalAction
{
    public function __construct(
        private readonly CreateMemberAction $createMember,
    ) {}

    public function execute(MemberProposal $proposal, User $reviewer, ?string $note = null): MemberProposal
    {
        if ($proposal->status !== MemberProposalStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending proposal can be approved.',
            ]);
        }

        if (Member::query()->where('email', $proposal->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A member with this email already exists.',
            ]);
        }

        return DB::transaction(function () use ($proposal, $reviewer, $note): MemberProposal {
            $member = $this->createMember->execute(new MemberData(
                name: $proposal->name,
                email: $proposal->email,
                status: MemberStatus::AwaitingInvitation,
            ));

            $proposal->forceFill([
                'status' => MemberProposalStatus::Approved,
                'member_id' => $member->id,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $proposal;
        });
    }
}
