<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Rominas\Academy\Member\Model\Member;
use Rominas\Delivery\Enums\EmailAudience;
use SensitiveParameter;

class AcademyNominationFollowUpEmailPayloadFactory extends MailPayloadFactory
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
            . '/autentificare?token=' . $this->token
            . '&email=' . rawurlencode($this->email);

        return view($this->templateConfig()['view'], [
            'name' => $this->memberName(),
            'url' => $url,
        ])->render();
    }

    protected function tags(): array
    {
        return ['academy-nomination-follow-up'];
    }

    /**
     * @return array{label: string, view: string, subject: string}
     */
    private function templateConfig(): array
    {
        /** @var array{label: string, view: string, subject: string} $template */
        $template = config(
            'academy-nomination-follow-ups.templates.' . $this->template
        );

        return $template;
    }

    private function memberName(): ?string
    {
        return Member::query()
            ->where('email', $this->email)
            ->value('name');
    }
}
