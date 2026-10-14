<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use SensitiveParameter;

/**
 * The public voting-link e-mail. The link carries only the single-use token to the voting SPA's ballot
 * screen — no email or identifier in the URL, since the token alone authorizes the accountless voter. The
 * recipient email is used to send the message and is not embedded in the link.
 */
class VotingLinkEmailPayloadFactory extends MailPayloadFactory
{
    public const string SUBJECT = 'Link de vot The Romanian Music Industry Awards';

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
        $url = rtrim((string) config('services.frontend.voting_url'), '/') . '/vot?token=' . $this->token;

        return view('emails.voting.link', [
            'url' => $url,
        ])->render();
    }

    protected function tags(): array
    {
        return ['voting-link'];
    }
}
