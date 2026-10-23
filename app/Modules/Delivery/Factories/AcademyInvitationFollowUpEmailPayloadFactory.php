<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Rominas\Academy\Member\Model\Member;
use Rominas\Delivery\Enums\EmailAudience;
use SensitiveParameter;

class AcademyInvitationFollowUpEmailPayloadFactory extends MailPayloadFactory
{
    public function __construct(
        private readonly string $email,
        #[SensitiveParameter]
        private readonly string $token,
        private readonly string $template = 'ro',
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
        return $this->templateConfig()['subject'];
    }

    protected function body(): string
    {
        $url = rtrim((string) config('services.frontend.academy_url'), '/')
            . '/invitatie?token=' . $this->token
            . '&email=' . rawurlencode($this->email);

        return view($this->templateConfig()['view'], [
            'name' => Member::query()
                ->where('email', $this->email)
                ->value('name'),
            'url' => $url,
        ])->render();
    }

    protected function tags(): array
    {
        return ['academy-invitation-follow-up'];
    }

    /**
     * @return array{label: string, subject: string, view: string}
     */
    private function templateConfig(): array
    {
        return config(
            'academy-invitation-follow-ups.templates.' . $this->template,
        );
    }
}
