<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Rominas\Notifications\Enums\NotificationType;

/**
 * A fraud detection run recorded new alerts for an edition's public vote.
 */
class NewFraudAlerts extends AdminNotification
{
    public function __construct(
        public readonly int $editionId,
        public readonly string $editionName,
        public readonly int $newAlertCount,
    ) {
        $this->afterCommit();
    }

    public function type(): NotificationType
    {
        return NotificationType::NewFraudAlerts;
    }

    public function title(): string
    {
        return $this->newAlertCount === 1
            ? "1 new fraud alert for {$this->editionName}"
            : "{$this->newAlertCount} new fraud alerts for {$this->editionName}";
    }

    public function body(): string
    {
        return 'The latest check of the public vote found suspicious activity that needs review.';
    }

    public function url(): string
    {
        return "/fraud-monitoring/{$this->editionId}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            ...parent::toArray($notifiable),
            'edition_id' => $this->editionId,
            'new_alert_count' => $this->newAlertCount,
        ];
    }
}
