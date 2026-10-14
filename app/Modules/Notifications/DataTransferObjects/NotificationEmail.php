<?php

declare(strict_types=1);

namespace Rominas\Notifications\DataTransferObjects;

use Rominas\Delivery\Enums\EmailAudience;

/**
 * The e-mail form of a notification: its subject, the Blade view (with data) that renders the body and the
 * audience it is for, which picks the sender name.
 * Handed by a notification to the {@see \Rominas\Notifications\Channels\DeliveryChannel}.
 */
final readonly class NotificationEmail
{
    /**
     * @param  array<string, mixed>  $viewData
     */
    public function __construct(
        public string $subject,
        public string $view,
        public EmailAudience $audience,
        public array $viewData = [],
    ) {}
}
