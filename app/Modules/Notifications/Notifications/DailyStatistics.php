<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Rominas\Delivery\Enums\EmailAudience;
use Rominas\Notifications\DataTransferObjects\NotificationEmail;
use Rominas\Notifications\Enums\NotificationType;

/**
 * The daily totals of the running edition: each metric's count for the previous day and overall.
 * A metric with no per-day value (a point-in-time count) carries `null` for the day.
 */
class DailyStatistics extends AdminNotification
{
    /**
     * @param  string  $day  the day the statistics cover, as shown to the reader (e.g. `9 Oct 2026`)
     * @param  list<array{metric: string, day: int|null, total: int}>  $rows
     */
    public function __construct(
        public readonly int $editionId,
        public readonly string $editionName,
        public readonly string $day,
        public readonly array $rows,
    ) {
        $this->afterCommit();
    }

    public function type(): NotificationType
    {
        return NotificationType::DailyStatistics;
    }

    public function title(): string
    {
        return "Daily statistics for {$this->editionName}: {$this->day}";
    }

    public function body(): string
    {
        $parts = [];

        foreach ($this->rows as $row) {
            $parts[] = $row['day'] === null
                ? "{$row['metric']}: {$row['total']}"
                : "{$row['metric']}: {$row['day']} ({$row['total']} in total)";
        }

        return implode('. ', $parts) . '.';
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
        return [...parent::toArray($notifiable), 'edition_id' => $this->editionId];
    }

    public function toDelivery(object $notifiable): NotificationEmail
    {
        return new NotificationEmail(
            subject: $this->title(),
            view: 'emails.notifications.admin-daily-statistics',
            audience: EmailAudience::Admin,
            viewData: [
                'title' => $this->title(),
                'day' => $this->day,
                'rows' => $this->rows,
                'url' => $this->absoluteUrl(),
            ],
        );
    }
}
