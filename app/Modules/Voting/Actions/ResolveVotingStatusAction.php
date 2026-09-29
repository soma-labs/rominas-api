<?php

declare(strict_types=1);

namespace Rominas\Voting\Actions;

use Rominas\Editions\Model\Edition;
use Rominas\Voting\DataTransferObjects\VotingStatusData;
use Rominas\Voting\Enums\VotingState;

/**
 * Reports where public voting stands for the active edition, without throwing — so the public voting
 * frontend can say "opens on …" / "closed" before anyone requests a link. Uses the same
 * {@see VotingState::of()} rule as {@see ResolveOpenVotingEditionAction}'s gate.
 */
class ResolveVotingStatusAction
{
    public function execute(): VotingStatusData
    {
        $edition = Edition::query()->active()->first();

        return new VotingStatusData(VotingState::of($edition, now()), $edition);
    }
}
