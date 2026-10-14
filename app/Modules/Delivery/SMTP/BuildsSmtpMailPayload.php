<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\EmailContact;
use Rominas\Delivery\MailPayload;

/**
 * Makes a {@see \Rominas\Delivery\Factories\MailPayloadFactory} produce the payload `SmtpMailService` sends.
 */
trait BuildsSmtpMailPayload
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
        return new SmtpMailPayload(
            sender: $sender,
            recipients: $recipients,
            subject: $subject,
            body: $body,
            tags: $tags,
        );
    }
}
