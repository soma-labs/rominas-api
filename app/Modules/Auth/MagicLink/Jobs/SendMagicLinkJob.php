<?php

declare(strict_types=1);

namespace Rominas\Auth\MagicLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Rominas\Auth\MagicLink\Actions\SendMagicLinkAction;

/**
 * Thin async envelope over {@see SendMagicLinkAction} — keeps token issuing + email delivery off the
 * request thread. Carries only the email + guard + the Delivery action key (which email template to
 * use, e.g. an invitation vs a returning-login link), plus an optional template variant that e-mail offers.
 */
class SendMagicLinkJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $guard,
        private readonly string $email,
        private readonly string $emailAction,
        private readonly ?string $emailTemplate = null,
    ) {}

    public function handle(SendMagicLinkAction $action): void
    {
        $action->execute($this->guard, $this->email, $this->emailAction, $this->emailTemplate);
    }
}
