<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Illuminate\Support\Str;
use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\EmailContact;
use Rominas\Delivery\PayloadFactoryInterface;

/**
 * Renders the returning-login magic-link email for an existing academy member. The link carries the
 * raw magic-link token + email to the academy SPA's login screen, which verifies them for a token.
 */
class SmtpAcademyMagicLinkEmailPayloadFactory implements PayloadFactoryInterface
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
    ) {}

    public function create(): DeliveryPayloadInterface
    {
        $base = config('services.frontend.academy_url');

        $url = rtrim((string) $base, '/')
            . '/autentificare?token=' . $this->token
            . '&email=' . rawurlencode($this->email);

        $body = view('emails.academy.magic-link', [
            'name' => Str::before($this->email, '@'),
            'url' => $url,
        ])->render();

        return new SmtpMailPayload(
            sender: new EmailContact(
                email: config('mail.from.address'),
                name: config('mail.from.name'),
            ),
            recipients: [$this->email],
            subject: 'Link de autentificare Rominas',
            body: $body,
        );
    }
}
