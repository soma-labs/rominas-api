<?php

declare(strict_types=1);

namespace Rominas\Voting\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Voting\Enums\VotingState;

/**
 * Resolves the single active edition the public may vote in, enforcing the window gate: voting is open
 * only when that edition's status is `voting_open` AND now falls within `[voting_start_at, voting_end_at]`
 * (see {@see VotingState::of()}, shared with the public status endpoint). Throws a 422 otherwise. Mirrors
 * ResolveOpenNominationEditionAction for the voting phase.
 */
class ResolveOpenVotingEditionAction
{
    public function execute(): Edition
    {
        $edition = Edition::query()->active()->first();

        if (VotingState::of($edition, now()) === VotingState::Open) {
            /** @var Edition $edition */
            return $edition;
        }

        throw ValidationException::withMessages([
            'voting' => $edition?->status === EditionStatus::VotingOpen
                ? 'The voting window is closed.'
                : 'Voting is not open.',
        ]);
    }
}
