<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Illuminate\Support\Str;
use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\EmailContact;
use Rominas\Delivery\PayloadFactoryInterface;

/**
 * Renders the academy-member invitation email. The link carries the raw magic-link token + email to
 * the academy SPA's onboarding screen, which verifies them and activates the member on first login.
 */
class SmtpAcademyInvitationEmailPayloadFactory implements PayloadFactoryInterface
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
    ) {}

    public function create(): DeliveryPayloadInterface
    {
        $base = config('services.frontend.academy_url');

        $url = rtrim((string) $base, '/')
            . '/invitatie?token=' . $this->token
            . '&email=' . rawurlencode($this->email);

        $body = view('emails.academy.invitation', [
            'name' => Str::before($this->email, '@'),
            'url' => $url,
        ])->render();

        return new SmtpMailPayload(
            sender: new EmailContact(
                email: config('mail.from.address'),
                name: config('mail.from.name'),
            ),
            recipients: [$this->email],
            subject: 'Invitație în Academia Romias',
            body: $body,
        );
    }
}
