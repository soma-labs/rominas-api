<?php

declare(strict_types=1);

namespace Rominas\Voting\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Voting\Model\Ballot;

/**
 * Resolves the ballot a one-time link token authorizes, for the currently open voting edition. The token
 * must correspond to an `issued` (not yet used) ballot that has not expired. On any failure — unknown,
 * already used, or expired — a single generic 422 is thrown (no detail, so a used vs. missing token can't
 * be distinguished), mirroring the academy magic-link verify. The resolved ballot carries its edition.
 */
class ResolveBallotByTokenAction
{
    public function __construct(
        private readonly ResolveOpenVotingEditionAction $resolveOpenVoting,
    ) {}

    public function execute(string $token): Ballot
    {
        $edition = $this->resolveOpenVoting->execute();

        $ballot = Ballot::query()
            ->forEdition($edition)
            ->byTokenHash(hash('sha256', $token))
            ->issued()
            ->first();

        if ($ballot === null || $ballot->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => __('This link is invalid, has expired or has already been used.'),
            ]);
        }

        $ballot->setRelation('edition', $edition);

        return $ballot;
    }
}
