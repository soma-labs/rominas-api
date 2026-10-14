<?php

declare(strict_types=1);

namespace Rominas\Delivery\Factories;

use Illuminate\Support\Str;

/**
 * Any notification e-mail (Notifications module): the notification supplies the subject and the Blade
 * view with its data, so one factory serves every notification type. The tag is the view's last segment.
 */
class NotificationEmailPayloadFactory extends MailPayloadFactory
{
    /**
     * @param  array<string, mixed>  $viewData
     */
    public function __construct(
        private readonly string $email,
        private readonly string $subject,
        private readonly string $view,
        private readonly array $viewData = [],
    ) {}

    protected function recipients(): array
    {
        return [$this->email];
    }

    protected function subject(): string
    {
        return $this->subject;
    }

    protected function body(): string
    {
        return view($this->view, $this->viewData)->render();
    }

    protected function tags(): array
    {
        return [Str::afterLast($this->view, '.')];
    }
}
