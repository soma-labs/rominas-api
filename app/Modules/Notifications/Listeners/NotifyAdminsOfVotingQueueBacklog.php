<?php

declare(strict_types=1);

namespace Rominas\Notifications\Listeners;

use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Cache;
use Rominas\Notifications\Actions\NotifyAdminsWithPermissionAction;
use Rominas\Notifications\Notifications\VotingQueueBacklog;

/**
 * Tells the users who see reports that a voting queue is backed up. `queue:monitor` (scheduled every
 * minute in routes/console.php) fires QueueBusy for every queue over its limit on every run, so each queue
 * alerts at most once per `voting.queue_alert_cooldown_minutes`. Other queues are ignored. Wired in
 * EventServiceProvider.
 */
class NotifyAdminsOfVotingQueueBacklog
{
    public function __construct(
        private readonly NotifyAdminsWithPermissionAction $notifyAdmins,
    ) {}

    public function handle(QueueBusy $event): void
    {
        if (! in_array($event->queue, (array) config('voting.queues'), true)) {
            return;
        }

        $cooldownStarted = Cache::add(
            'voting:queue-backlog-alerted:' . $event->queue,
            true,
            now()->addMinutes((int) config('voting.queue_alert_cooldown_minutes')),
        );

        if (! $cooldownStarted) {
            return;
        }

        $this->notifyAdmins->execute('reporting', new VotingQueueBacklog(
            queueName: $event->queue,
            waitingJobs: $event->size,
        ));
    }
}
