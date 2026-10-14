<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo;

use Rominas\Delivery\EmailContact;
use Rominas\Delivery\MailPayload;

/**
 * Makes a {@see \Rominas\Delivery\Factories\MailPayloadFactory} produce the payload `BrevoMailService` sends.
 */
trait BuildsBrevoMailPayload
{
    /**
     * @param  list<string>  $recipients
     * @param  list<string>  $tags
     */
    protected function mailPayload(
        EmailContact $sender,
        array $recipients,
        string $subject,
        string $body,
        array $tags,
    ): MailPayload {
        return new BrevoMailPayload(
            sender: $sender,
            recipients: $recipients,
            subject: $subject,
            body: $body,
            tags: $tags,
        );
    }
}
