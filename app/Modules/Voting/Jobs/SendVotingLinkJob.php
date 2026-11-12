<?php

declare(strict_types=1);

namespace Rominas\Voting\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Rominas\Delivery\Actions\DeliveryAction;

/**
 * Thin async envelope that emails a voting link, on the voting mail queue (`voting.queues.mail`). Carries
 * the recipient email and the plaintext token (needed to build the link URL); neither is persisted — the
 * ballot row stores only hashes. The token is already stored (hashed) by IssueVotingLinkAction before this
 * job is dispatched, so a failed send is retried here without a second e-mail validation.
 */
class SendVotingLinkJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $email,
        private readonly string $token,
    ) {
        $this->onQueue(config('voting.queues.mail'));
    }

    public function handle(DeliveryAction $delivery): void
    {
        $delivery->execute('voting-link-email', ['email'], $this->email, $this->token);
    }
}
