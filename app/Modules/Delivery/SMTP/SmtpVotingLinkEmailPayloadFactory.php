<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\EmailContact;
use Rominas\Delivery\PayloadFactoryInterface;

/**
 * Renders the public voting-link email. The link carries only the single-use token to the voting SPA's
 * ballot screen — no email or identifier in the URL, since the token alone authorizes the accountless
 * voter. The recipient email is used to send the message and is not embedded in the link.
 */
class SmtpVotingLinkEmailPayloadFactory implements PayloadFactoryInterface
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
    ) {}

    public function create(): DeliveryPayloadInterface
    {
        $base = config('services.frontend.voting_url');

        $url = rtrim((string) $base, '/') . '/vot?token=' . $this->token;

        $body = view('emails.voting.link', [
            'url' => $url,
        ])->render();

        return new SmtpMailPayload(
            sender: new EmailContact(
                email: config('mail.from.address'),
                name: config('mail.from.name'),
            ),
            recipients: [$this->email],
            subject: 'Link de vot Romias',
            body: $body,
        );
    }
}
