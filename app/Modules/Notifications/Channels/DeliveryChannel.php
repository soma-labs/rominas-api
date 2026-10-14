<?php

declare(strict_types=1);

namespace Rominas\Notifications\Channels;

use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use Rominas\Delivery\Actions\DeliveryAction;
use Rominas\Notifications\Contracts\DeliverableByEmail;

/**
 * Sends a notification's e-mail through the Delivery module, so notification e-mails use the same
 * transport (SMTP today) as every other e-mail the API sends. A delivery failure throws, which fails
 * the queued channel job so it is retried.
 */
class DeliveryChannel
{
    public function __construct(
        private readonly DeliveryAction $delivery,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof DeliverableByEmail) {
            throw new InvalidArgumentException($notification::class . ' cannot be delivered by e-mail.');
        }

        $address = $notifiable->routeNotificationFor('mail', $notification);

        if (! is_string($address) || $address === '') {
            return;
        }

        $email = $notification->toDelivery($notifiable);

        $this->delivery->execute(
            'notification-email',
            ['email'],
            $address,
            $email->subject,
            $email->view,
            $email->audience,
            $email->viewData,
        );
    }
}
