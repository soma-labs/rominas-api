<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\EmailContact;
use Rominas\Delivery\MailPayload;
use Rominas\Delivery\PayloadFactoryInterface;

/**
 * Base of every e-mail payload factory. A subclass per e-mail says what is sent (recipients, subject,
 * rendered body, tags); a transport trait (`BuildsSmtpMailPayload`, `BuildsBrevoMailPayload`) says which
 * payload class carries it, so the SMTP and Brevo factories of one e-mail can never differ in content.
 * The sender is always the configured `mail.from` address.
 */
abstract class MailPayloadFactory implements PayloadFactoryInterface
{
    public function create(): DeliveryPayloadInterface
    {
        return $this->mailPayload(
            sender: new EmailContact(
                email: config('mail.from.address'),
                name: config('mail.from.name'),
            ),
            recipients: $this->recipients(),
            subject: $this->subject(),
            body: $this->body(),
            tags: $this->tags(),
        );
    }

    /**
     * @return list<string>
     */
    abstract protected function recipients(): array;

    abstract protected function subject(): string;

    abstract protected function body(): string;

    /**
     * Labels the e-mail in the provider's logs (Brevo shows them; SMTP ignores them).
     *
     * @return list<string>
     */
    abstract protected function tags(): array;

    /**
     * @param  list<string>  $recipients
     * @param  list<string>  $tags
     */
    abstract protected function mailPayload(
        EmailContact $sender,
        array $recipients,
        string $subject,
        string $body,
        array $tags,
    ): MailPayload;
}
