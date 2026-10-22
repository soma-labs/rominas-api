<?php

declare(strict_types=1);

namespace Rominas\Academy\Actions;

use Illuminate\Support\Facades\DB;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Auth\MagicLink\Jobs\SendMagicLinkJob;

class SendSelectedMemberInvitationFollowUpsAction
{
    /**
     * @param list<int> $memberIds
     */
    public function execute(array $memberIds, string $template): int
    {
        return DB::transaction(function () use ($memberIds, $template): int {
            $members = Member::query()
                ->whereIn('id', $memberIds)
                ->filterByStatus(MemberStatus::Invited)
                ->get();

            foreach ($members as $member) {
                SendMagicLinkJob::dispatch(
                    'member',
                    $member->email,
                    'academy-invitation-follow-up-email',
                    $template,
                )->afterCommit();
            }

            return $members->count();
        });
    }
}
