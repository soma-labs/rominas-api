<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\Factories\NotificationEmailPayloadFactory;

/**
 * The notification e-mail sent over SMTP. The content lives in {@see NotificationEmailPayloadFactory}.
 */
class SmtpNotificationEmailPayloadFactory extends NotificationEmailPayloadFactory
{
    use BuildsSmtpMailPayload;
}
