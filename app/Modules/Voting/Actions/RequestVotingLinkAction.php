<?php

declare(strict_types=1);

namespace Rominas\Voting\Actions;

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
 * Issues a one-time voting link for an email address. Requires voting to be open. The voter is
 * pseudonymized to an `email_hash`; the link is a high-entropy token stored only as its SHA-256.
 *
 * One link, ever, per email: if a ballot already exists for this edition + email, nothing is issued and
 * we return silently, so the caller can answer a generic 200 without revealing whether the address
 * has already requested or voted. The plaintext token is handed to the queued delivery job and never stored.
 *
 * A new address is first checked by the e-mail validation service. A rejected one gets no link and a
 * visible 422 on `email` (one generic message, never the reason); an accepted one gets its link, with the
 * service's verdict kept on the ballot for fraud review. If the service cannot answer, the link is issued
 * anyway and the ballot is left unchecked — validation never blocks the vote.
 */
class RequestVotingLinkAction
{
    public function __construct(
        private readonly ResolveOpenVotingEditionAction $resolveOpenVoting,
        private readonly EmailValidatorInterface $emailValidator,
        private readonly ResolveEmailCheckDecisionAction $resolveEmailCheckDecision,
    ) {}

    public function execute(string $email): void
    {
        $edition = $this->resolveOpenVoting->execute();

        $emailHash = VoterHasher::emailHash($email);

        if (Ballot::query()->forEdition($edition)->byEmailHash($emailHash)->exists()) {
            return;
        }

        // Keyed by the hash, so the address itself never sits in the cache.
        $rejectionCacheKey = 'voting:email-rejected:' . $emailHash;

        if (Cache::has($rejectionCacheKey)) {
            $this->rejectEmail();
        }

        $result = $this->emailValidator->validate(trim($email));
        $decision = $result === null ? null : $this->resolveEmailCheckDecision->execute($result);

        if ($decision === EmailCheckDecision::Rejected) {
            Cache::put(
                $rejectionCacheKey,
                true,
                now()->addMinutes((int) config('voting.email_validation.rejection_cache_minutes')),
            );

            $this->rejectEmail();
        }

        $token = Str::random(48);

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

        SendVotingLinkJob::dispatch($email, $token)->afterCommit();
    }

    /**
     * @throws ValidationException
     */
    private function rejectEmail(): never
    {
        throw ValidationException::withMessages([
            'email' => __('This email address cannot be used to vote. Please use a different address.'),
        ]);
    }
}
