<?php

declare(strict_types=1);

namespace Rominas\Voting\Actions;

use Illuminate\Support\Facades\Cache;
use Rominas\Voting\Jobs\IssueVotingLinkJob;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Support\VoterHasher;

/**
 * Accepts a request for a one-time voting link and queues the work, so the request never waits on the
 * e-mail validation service. Requires voting to be open (a closed vote is a visible 422).
 *
 * The caller always answers the same generic 200: the response never reveals whether the address has
 * already requested a link or voted, or whether it was rejected. Only the cheap checks happen here, on the
 * email hash: an address with a ballot, or one rejected recently, queues nothing. Everything else —
 * validation, the ballot and the link e-mail — is IssueVotingLinkJob's.
 */
class RequestVotingLinkAction
{
    public function __construct(
        private readonly ResolveOpenVotingEditionAction $resolveOpenVoting,
    ) {}

    public function execute(string $email): void
    {
        $edition = $this->resolveOpenVoting->execute();

        $emailHash = VoterHasher::emailHash($email);

        if (Ballot::query()->forEdition($edition)->byEmailHash($emailHash)->exists()) {
            return;
        }

        if (Cache::has(VoterHasher::rejectionCacheKey($emailHash))) {
            return;
        }

        IssueVotingLinkJob::dispatch($email);
    }
}
