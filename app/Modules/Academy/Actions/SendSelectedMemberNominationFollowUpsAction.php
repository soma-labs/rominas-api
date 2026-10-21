<?php

declare(strict_types=1);

namespace Rominas\Academy\Actions;

use Illuminate\Support\Facades\DB;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Auth\MagicLink\Jobs\SendMagicLinkJob;
use Rominas\Editions\Model\Edition;

class SendSelectedMemberNominationFollowUpsAction
{
    /**
     * @param list<int> $memberIds
     */
    public function execute(array $memberIds, string $template): int
    {
        $edition = Edition::query()->active()->firstOrFail();

        return DB::transaction(function () use ($memberIds, $template, $edition): int {
            $members = Member::query()
                ->whereIn('id', $memberIds)
                ->filterByStatus(MemberStatus::Active)
                ->withoutSubmittedNominationFor($edition)
                ->get();

            foreach ($members as $member) {
                SendMagicLinkJob::dispatch(
                    'member',
                    $member->email,
                    'academy-nomination-follow-up-email',
                    $template,
                )->afterCommit();
            }

            return $members->count();
        });
    }
}
