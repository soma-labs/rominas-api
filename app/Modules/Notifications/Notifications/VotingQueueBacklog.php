<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Rominas\Notifications\Enums\NotificationType;

/**
 * A voting queue holds more jobs than `voting.queue_alert_threshold`: voters get their links late. Sent
 * by NotifyAdminsOfVotingQueueBacklog, at most once per queue per cooldown.
 */
class VotingQueueBacklog extends AdminNotification
{
    public function __construct(
        public readonly string $queueName,
        public readonly int $waitingJobs,
    ) {
        $this->afterCommit();
    }

    public function type(): NotificationType
    {
        return NotificationType::VotingQueueBacklog;
    }

    public function title(): string
    {
        return sprintf('Voting links are delayed: %s jobs waiting', number_format($this->waitingJobs));
    }

    public function body(): string
    {
        return sprintf(
            'The %s queue has %s jobs waiting. Voters still get their links, only later; if the queue keeps '
                . 'growing, the voting workers need checking or more capacity.',
            $this->queueName,
            number_format($this->waitingJobs),
        );
    }

    public function url(): string
    {
        return '/reporting';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            ...parent::toArray($notifiable),
            'queue' => $this->queueName,
            'waiting_jobs' => $this->waitingJobs,
        ];
    }
}
