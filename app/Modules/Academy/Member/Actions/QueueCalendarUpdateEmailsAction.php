<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Actions;

use Rominas\Academy\Member\Jobs\SendCalendarUpdateEmailJob;
use Rominas\Academy\Member\Model\Member;

/**
 * Queues the calendar update for every member, whatever their status.
 */
class QueueCalendarUpdateEmailsAction
{
    /**
     * @return int The number of e-mails queued.
     */
    public function execute(): int
    {
        $queuedEmailCount = 0;

        foreach (Member::query()->select(['id', 'email'])->lazyById() as $member) {
            SendCalendarUpdateEmailJob::dispatch($member->email);
            $queuedEmailCount++;
        }

        return $queuedEmailCount;
    }
}
