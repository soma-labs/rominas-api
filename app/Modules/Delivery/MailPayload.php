<?php

declare(strict_types=1);

namespace Rominas\Delivery;

/**
 * One e-mail, described independently of the transport. Each mail service turns it into its own request
 * and ignores what it cannot use (SMTP ignores the tags; Brevo shows them in its logs).
 */
class MailPayload implements DeliveryPayloadInterface
{
    /**
     * @param  list<string>  $recipients
     * @param  list<array{path: string, name: string}>  $attachments  each path a URL or a local file
     * @param  list<EmailContact>  $cc
     * @param  list<string>  $tags
     */
    public function __construct(
        public EmailContact $sender,
        public array $recipients,
        public string $subject,
        public string $body,
        public array $attachments = [],
        public array $cc = [],
        public array $tags = [],
    ) {}
}
