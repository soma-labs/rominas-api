<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP\Factories;

use Rominas\Delivery\Factories\NotificationEmailPayloadFactory;
use Rominas\Delivery\SMTP\BuildsSmtpMailPayload;

/**
 * The notification e-mail sent over SMTP. The content lives in {@see NotificationEmailPayloadFactory}.
 */
class SmtpNotificationEmailPayloadFactory extends NotificationEmailPayloadFactory
{
    use BuildsSmtpMailPayload;
}
