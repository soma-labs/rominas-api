<?php

declare(strict_types=1);

namespace Rominas\Academy\Actions;

use Illuminate\Support\Facades\DB;
use Rominas\Academy\Member\Actions\InviteMemberAction;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;

/**
 * Sends the invitation e-mail, in the chosen template (config/academy-invitations.php), to each of the
 * given members awaiting an invitation or already invited; the first move to `Invited`, a re-sent
 * member keeps their status (`invited_at` is re-stamped). One e-mail per member, through
 * {@see InviteMemberAction}; available via `POST /api/admin/members/invitations/send`. Returns the number
 * of members invited.
 */
class SendSelectedMemberInvitationsAction
{
    public function __construct(
        private readonly InviteMemberAction $inviteMember,
    ) {}

    /**
     * @param  list<int>  $memberIds
     */
    public function execute(array $memberIds, string $template): int
    {
        return DB::transaction(function () use ($memberIds, $template): int {
            $members = Member::query()
                ->whereIn('id', $memberIds)
                ->filterByStatuses(...MemberStatus::invitable())
                ->get();

            $members->each(fn(Member $member): Member => $this->inviteMember->execute($member, $template));

            return $members->count();
        });
    }
}
