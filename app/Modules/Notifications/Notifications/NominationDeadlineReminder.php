<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Rominas\Delivery\Enums\EmailAudience;
use Rominas\Notifications\DataTransferObjects\NotificationEmail;
use Rominas\Notifications\Enums\NotificationType;

/**
 * Reminds an academy member who has not submitted their nominations that the deadline is near.
 */
class NominationDeadlineReminder extends MemberNotification
{
    /**
     * @param  string  $deadline  the nomination deadline in Romanian time, as shown to the reader
     */
    public function __construct(
        public readonly int $editionId,
        public readonly string $editionName,
        public readonly int $daysBefore,
        public readonly string $deadline,
    ) {
        $this->afterCommit();
    }

    public function type(): NotificationType
    {
        return NotificationType::NominationDeadlineReminder;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'edition_id' => $this->editionId,
            'days_before' => $this->daysBefore,
        ];
    }

    public function toDelivery(object $notifiable): NotificationEmail
    {
        return new NotificationEmail(
            subject: $this->daysBefore === 1
                ? "Mâine se încheie nominalizările {$this->editionName}!"
                : "Mai sunt {$this->daysBefore} zile pentru nominalizările {$this->editionName}!",
            view: 'emails.notifications.academy.nomination-reminder',
            audience: EmailAudience::Academy,
            viewData: [
                'editionName' => $this->editionName,
                'daysBefore' => $this->daysBefore,
                'deadline' => $this->deadline,
                'url' => $this->academyUrl(),
            ],
        );
    }
}
