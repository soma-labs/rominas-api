<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\Factories\ExampleNotificationPayloadFactory;

/**
 * The example e-mail sent over SMTP. The content lives in {@see ExampleNotificationPayloadFactory}.
 */
class SmtpExampleNotificationPayloadFactory extends ExampleNotificationPayloadFactory
{
    use BuildsSmtpMailPayload;
}
