<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo\Factories;

use Rominas\Delivery\Factories\NotificationEmailPayloadFactory;
use Rominas\Delivery\Brevo\BuildsBrevoMailPayload;

/**
 * The notification e-mail sent through the Brevo API. The content lives in {@see NotificationEmailPayloadFactory}.
 */
class BrevoNotificationEmailPayloadFactory extends NotificationEmailPayloadFactory
{
    use BuildsBrevoMailPayload;
}
