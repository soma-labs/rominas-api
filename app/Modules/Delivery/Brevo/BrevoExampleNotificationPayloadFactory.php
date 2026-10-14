<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo;

use Rominas\Delivery\Factories\ExampleNotificationPayloadFactory;

/**
 * The example e-mail sent through the Brevo API. The content lives in {@see ExampleNotificationPayloadFactory}.
 */
class BrevoExampleNotificationPayloadFactory extends ExampleNotificationPayloadFactory
{
    use BuildsBrevoMailPayload;
}
