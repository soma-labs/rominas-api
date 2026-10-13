<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Rominas\Notifications\DataTransferObjects\NotificationEmail;
use Rominas\Notifications\Enums\NotificationType;

/**
 * Tells an academy member that the edition's nominations are open.
 */
class NominationsOpened extends MemberNotification
{
    /**
     * @param  string  $deadline  the nomination deadline in Romanian time, as shown to the reader
     */
    public function __construct(
        public readonly int $editionId,
        public readonly string $editionName,
        public readonly string $deadline,
    ) {
        $this->afterCommit();
    }

    public function type(): NotificationType
    {
        return NotificationType::NominationsOpened;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['edition_id' => $this->editionId];
    }

    public function toDelivery(object $notifiable): NotificationEmail
    {
        return new NotificationEmail(
            subject: "Nominalizările {$this->editionName} sunt deschise!",
            view: 'emails.notifications.academy.nominations-opened',
            viewData: [
                'editionName' => $this->editionName,
                'deadline' => $this->deadline,
                'url' => $this->academyUrl(),
            ],
        );
    }
}
