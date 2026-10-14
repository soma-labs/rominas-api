<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Actions;

use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Auth\MagicLink\Jobs\SendMagicLinkJob;

/**
 * Sends one member their invitation: a queued {@see SendMagicLinkJob} on the `member` guard, dispatched
 * after the surrounding transaction commits so the job never races a not-yet-persisted row. A member
 * awaiting their first invitation moves to `Invited`; a resend leaves an invited/active/suspended member's
 * status alone. `invited_at` is stamped either way.
 */
class InviteMemberAction
{
    public function execute(Member $member): Member
    {
        SendMagicLinkJob::dispatch('member', $member->email, 'academy-invitation-email')
            ->afterCommit();

        $member->forceFill([
            'status' => $member->status === MemberStatus::AwaitingInvitation
                ? MemberStatus::Invited
                : $member->status,
            'invited_at' => now(),
        ])->save();

        return $member;
    }
}
