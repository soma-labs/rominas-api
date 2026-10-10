<?php

declare(strict_types=1);

namespace Rominas\Notifications\Contracts;

use Rominas\Notifications\DataTransferObjects\NotificationEmail;

/**
 * A notification that can be sent through the {@see \Rominas\Notifications\Channels\DeliveryChannel}.
 */
interface DeliverableByEmail
{
    public function toDelivery(object $notifiable): NotificationEmail;
}
