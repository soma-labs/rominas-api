<?php

declare(strict_types=1);

namespace Rominas\Academy\Actions;

use Rominas\Academy\Member\Actions\InviteMemberAction;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;

/**
 * Sends an invitation email to every academy member still awaiting one (`AwaitingInvitation` status) and
 * moves them to `Invited`. Invitations are a manual admin operation, available via
 * `POST /api/admin/members/invitations` — deliberately not tied to the edition lifecycle. Each member is
 * invited through {@see InviteMemberAction}. Returns the number of members invited.
 */
class SendAcademyInvitationsAction
{
    public function __construct(
        private readonly InviteMemberAction $inviteMember,
    ) {}

    public function execute(): int
    {
        $members = Member::query()
            ->filterByStatus(MemberStatus::AwaitingInvitation)
            ->get();

        $members->each(fn(Member $member): Member => $this->inviteMember->execute($member));

        return $members->count();
    }
}
