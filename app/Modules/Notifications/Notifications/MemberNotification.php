<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Rominas\Notifications\Channels\DeliveryChannel;
use Rominas\Notifications\Contracts\DeliverableByEmail;
use Rominas\Notifications\Enums\NotificationType;

/**
 * Base of every notification for academy members: an e-mail in Romanian. The stored row is only a
 * record of what was sent (it keeps a reminder from going out twice) and is not served by any endpoint.
 */
abstract class MemberNotification extends Notification implements DeliverableByEmail, ShouldQueue
{
    use Queueable;

    abstract public function type(): NotificationType;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', DeliveryChannel::class];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type()->value;
    }

    protected function academyUrl(): string
    {
        return rtrim((string) config('services.frontend.academy_url'), '/');
    }
}
