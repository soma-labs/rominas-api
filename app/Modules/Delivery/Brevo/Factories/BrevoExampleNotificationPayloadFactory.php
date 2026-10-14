<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo\Factories;

use Rominas\Delivery\Factories\ExampleNotificationPayloadFactory;
use Rominas\Delivery\Brevo\BuildsBrevoMailPayload;

/**
 * The example e-mail sent through the Brevo API. The content lives in {@see ExampleNotificationPayloadFactory}.
 */
class BrevoExampleNotificationPayloadFactory extends ExampleNotificationPayloadFactory
{
    use BuildsBrevoMailPayload;
}
