<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

/**
 * Generic, domain-neutral example: demonstrates the action => factory => service pipeline without
 * depending on any domain model or Blade view. The body is sent as given.
 */
class ExampleNotificationPayloadFactory extends MailPayloadFactory
{
    public function __construct(
        private readonly string $recipientEmail,
        private readonly string $subject,
        private readonly string $body,
    ) {}

    protected function recipients(): array
    {
        return [$this->recipientEmail];
    }

    protected function subject(): string
    {
        return $this->subject;
    }

    protected function body(): string
    {
        return $this->body;
    }

    protected function tags(): array
    {
        return ['example-notification'];
    }
}
