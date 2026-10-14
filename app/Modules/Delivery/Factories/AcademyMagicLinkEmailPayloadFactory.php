<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Rominas\Academy\Member\Model\Member;
use SensitiveParameter;

/**
 * The returning-login magic-link e-mail for an existing academy member. The link carries the raw
 * magic-link token + email to the academy SPA's login screen, which verifies them for a token. The
 * greeting uses the member's name.
 */
class AcademyMagicLinkEmailPayloadFactory extends MailPayloadFactory
{
    public const string SUBJECT = 'Link de autentificare în Academia The Romanian Music Industry Awards';

    public function __construct(
        private readonly string $email,
        #[SensitiveParameter]
        private readonly string $token,
    ) {}

    protected function recipients(): array
    {
        return [$this->email];
    }

    protected function subject(): string
    {
        return self::SUBJECT;
    }

    protected function body(): string
    {
        $url = rtrim((string) config('services.frontend.academy_url'), '/')
            . '/autentificare?token=' . $this->token
            . '&email=' . rawurlencode($this->email);

        return view('emails.academy.magic-link', [
            'name' => $this->memberName(),
            'url' => $url,
        ])->render();
    }

    protected function tags(): array
    {
        return ['academy-login'];
    }

    private function memberName(): ?string
    {
        return Member::query()->where('email', $this->email)->value('name');
    }
}
