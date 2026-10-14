<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Illuminate\Support\Str;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use SensitiveParameter;

/**
 * The academy-member invitation e-mail. The link carries the raw magic-link token + email to the academy
 * SPA's onboarding screen, which verifies them and activates the member on first login. A member who came
 * from an approved proposal is told who proposed them.
 */
class AcademyInvitationEmailPayloadFactory extends MailPayloadFactory
{
    public const string SUBJECT = 'Invitație în Academia The Romanian Music Industry Awards';

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
            . '/invitatie?token=' . $this->token
            . '&email=' . rawurlencode($this->email);

        return view('emails.academy.invitation', [
            'name' => Str::before($this->email, '@'),
            'url' => $url,
            'proposerName' => $this->proposerName(),
        ])->render();
    }

    protected function tags(): array
    {
        return ['academy-invitation'];
    }

    private function proposerName(): ?string
    {
        return MemberProposal::query()
            ->approvedForMemberEmail($this->email)
            ->with('proposedBy')
            ->first()
            ?->proposedBy
            ?->name;
    }
}
