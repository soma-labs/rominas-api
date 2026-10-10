<?php

declare(strict_types=1);

namespace Rominas\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Rominas\Notifications\Channels\DeliveryChannel;
use Rominas\Notifications\Contracts\DeliverableByEmail;
use Rominas\Notifications\DataTransferObjects\NotificationEmail;
use Rominas\Notifications\Enums\NotificationType;

/**
 * Base of every notification for admin users: stored for the admin's unread list and sent by e-mail.
 * The text is English, like the admin itself, and is fixed when the notification is created. `url()`
 * is a path inside the admin app; the e-mail prefixes it with the admin's base URL.
 */
abstract class AdminNotification extends Notification implements DeliverableByEmail, ShouldQueue
{
    use Queueable;

    abstract public function type(): NotificationType;

    abstract public function title(): string;

    abstract public function body(): string;

    abstract public function url(): string;

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }

    public function toDelivery(object $notifiable): NotificationEmail
    {
        return new NotificationEmail(
            subject: $this->title(),
            view: 'emails.notifications.admin',
            viewData: [
                'title' => $this->title(),
                'body' => $this->body(),
                'url' => $this->absoluteUrl(),
            ],
        );
    }

    protected function absoluteUrl(): string
    {
        return rtrim((string) config('services.frontend.admin_url'), '/') . $this->url();
    }
}
