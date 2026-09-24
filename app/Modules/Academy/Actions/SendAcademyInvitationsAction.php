<?php

declare(strict_types=1);

namespace Rominas\Academy\Actions;

use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Auth\MagicLink\Jobs\SendMagicLinkJob;

/**
 * Sends a magic-link invitation email to every academy member still awaiting one (`AwaitingInvitation`
 * status) and moves them to `Invited`. Invitations are a manual admin operation, available via
 * `POST /api/admin/members/invitations` — deliberately not tied to the edition lifecycle. Each
 * invitation is a queued {@see SendMagicLinkJob} on the `member` guard, dispatched after the
 * surrounding transaction commits so the job never races a not-yet-persisted row. Returns the number
 * of members invited.
 */
class SendAcademyInvitationsAction
{
    public function execute(): int
    {
        $members = Member::query()
            ->filterByStatus(MemberStatus::AwaitingInvitation)
            ->get();

        $members->each(function (Member $member): void {
            SendMagicLinkJob::dispatch('member', $member->email, 'academy-invitation-email')
                ->afterCommit();

            $member->forceFill([
                'status' => MemberStatus::Invited,
                'invited_at' => now(),
            ])->save();
        });

        return $members->count();
    }
}
