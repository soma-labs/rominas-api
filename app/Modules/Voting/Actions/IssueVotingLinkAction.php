<?php

declare(strict_types=1);

namespace Rominas\Voting\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Rominas\EmailValidation\EmailValidatorInterface;
use Rominas\Voting\Enums\BallotStatus;
use Rominas\Voting\Enums\EmailCheckDecision;
use Rominas\Voting\Jobs\SendVotingLinkJob;
use Rominas\Voting\Model\Ballot;
use Rominas\Voting\Support\VoterHasher;

/**
 * Issues a one-time voting link for an email address, off the request (run by IssueVotingLinkJob). The
 * voter already got the generic answer, so nothing here is reported back to them.
 *
 * Checks again what may have changed since the request: voting must still be open and the address must
 * still have no ballot, which also makes a retried or duplicated job harmless. A new address is then
 * checked by the e-mail validation service. A rejected one gets no ballot and no link, and its hash is
 * remembered so a repeat request costs no second check. An accepted one gets its ballot, with the
 * service's verdict kept for fraud review. If the service cannot answer, the link is issued anyway and
 * the ballot is left unchecked: validation never blocks the vote.
 *
 * The plaintext token is handed to the queued mail job and never stored; the ballot keeps its SHA-256.
 */
class IssueVotingLinkAction
{
    public function __construct(
        private readonly ResolveOpenVotingEditionAction $resolveOpenVoting,
        private readonly EmailValidatorInterface $emailValidator,
        private readonly ResolveEmailCheckDecisionAction $resolveEmailCheckDecision,
    ) {}

    public function execute(string $email): void
    {
        try {
            $edition = $this->resolveOpenVoting->execute();
        } catch (ValidationException) {
            return;
        }

        $emailHash = VoterHasher::emailHash($email);

        if (Ballot::query()->forEdition($edition)->byEmailHash($emailHash)->exists()) {
            return;
        }

        $result = $this->emailValidator->validate(trim($email));
        $decision = $result === null ? null : $this->resolveEmailCheckDecision->execute($result);

        if ($decision === EmailCheckDecision::Rejected) {
            Cache::put(
                VoterHasher::rejectionCacheKey($emailHash),
                true,
                now()->addMinutes((int) config('voting.email_validation.rejection_cache_minutes')),
            );

            return;
        }

        $token = Str::random(48);

        try {
            Ballot::query()->create([
                'edition_id' => $edition->id,
                'email_hash' => $emailHash,
                'token_hash' => hash('sha256', $token),
                'status' => BallotStatus::Issued,
                'expires_at' => $edition->voting_end_at,
                'email_check_status' => $result?->status,
                'email_check_sub_status' => $result?->subStatus,
                'email_check_decision' => $decision,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another job for the same mailbox created the ballot first; that one sends the link.
            return;
        }

        SendVotingLinkJob::dispatch($email, $token)->afterCommit();
    }
}
