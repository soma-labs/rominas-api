<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Rominas\Delivery\EmailContact;
use Rominas\Delivery\MailPayload;
use Rominas\Delivery\PayloadFactoryInterface;

/**
 * Base of every e-mail payload factory. A subclass per e-mail says what is sent (recipients, subject,
 * rendered body, tags); every transport sends the same {@see MailPayload}, so switching transports never
 * changes an e-mail's content. The sender is always the configured `mail.from` address.
 */
abstract class MailPayloadFactory implements PayloadFactoryInterface
{
    public function create(): MailPayload
    {
        return new MailPayload(
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
}
