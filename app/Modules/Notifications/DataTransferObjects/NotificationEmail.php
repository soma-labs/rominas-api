<?php

declare(strict_types=1);

namespace Rominas\Notifications\DataTransferObjects;

/**
 * The e-mail form of a notification: its subject and the Blade view (with data) that renders the body.
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
        public array $viewData = [],
    ) {}
}
