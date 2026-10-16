<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Member\DataTransferObjects\InvitationTemplateData;
use Rominas\Academy\Member\Support\InvitationTemplates;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Delivery\Enums\EmailAudience;
use SensitiveParameter;

/**
 * The academy-member invitation e-mail. The link carries the raw magic-link token + email to the academy
 * SPA's onboarding screen, which verifies them and activates the member on first login. The greeting uses
 * the member's name; a member who came from an approved proposal is told who proposed them. The subject
 * and view come from the chosen template (config/academy-invitations.php); no template = the default.
 */
class AcademyInvitationEmailPayloadFactory extends MailPayloadFactory
{
    public function __construct(
        private readonly string $email,
        #[SensitiveParameter]
        private readonly string $token,
        private readonly ?string $template = null,
    ) {}

    protected function senderName(): string
    {
        return EmailAudience::Academy->senderName();
    }

    protected function recipients(): array
    {
        return [$this->email];
    }

    protected function subject(): string
    {
        return $this->template()->subject;
    }

    protected function body(): string
    {
        $url = rtrim((string) config('services.frontend.academy_url'), '/')
            . '/invitatie?token=' . $this->token
            . '&email=' . rawurlencode($this->email);

        return view($this->template()->view, [
            'name' => $this->memberName(),
            'url' => $url,
            'proposerName' => $this->proposerName(),
        ])->render();
    }

    protected function tags(): array
    {
        return ['academy-invitation'];
    }

    private function template(): InvitationTemplateData
    {
        return InvitationTemplates::find($this->template);
    }

    private function memberName(): ?string
    {
        return Member::query()->where('email', $this->email)->value('name');
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
