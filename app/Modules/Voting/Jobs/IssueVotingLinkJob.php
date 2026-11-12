<?php

declare(strict_types=1);

namespace Rominas\Voting\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Rominas\Voting\Actions\IssueVotingLinkAction;
use Rominas\Voting\Support\VoterHasher;

/**
 * Thin async envelope that runs IssueVotingLinkAction off the request thread, on the voting validation
 * queue (`voting.queues.validation`), so the e-mail check never holds a php-fpm worker.
 *
 * Unique per mailbox (keyed by the email hash, never the address) for 5 minutes: two quick requests for
 * one mailbox queue one job, so one validation credit is spent. Carries the address as typed, because
 * the link e-mail goes to it; nothing is stored in plaintext.
 */
class IssueVotingLinkJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 30;

    public int $uniqueFor = 300;

    public function __construct(
        private readonly string $email,
    ) {
        $this->onQueue(config('voting.queues.validation'));
    }

    public function uniqueId(): string
    {
        return VoterHasher::emailHash($this->email);
    }

    public function handle(IssueVotingLinkAction $issueVotingLink): void
    {
        $issueVotingLink->execute($this->email);
    }
}
