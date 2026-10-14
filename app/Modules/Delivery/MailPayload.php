<?php

declare(strict_types=1);

namespace Rominas\Delivery;

class MailPayload implements DeliveryPayloadInterface
{
    /**
     * @param  list<string>  $recipients
     * @param  list<array{path: string, name: string}>  $attachments  each path a URL or a local file
     * @param  list<EmailContact>  $cc
     */
    public function __construct(
        public EmailContact $sender,
        public array $recipients,
        public string $subject,
        public string $body,
        public array $attachments = [],
        public array $cc = [],
    ) {}
}
