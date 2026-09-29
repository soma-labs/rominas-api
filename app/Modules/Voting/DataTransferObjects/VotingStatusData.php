<?php

declare(strict_types=1);

namespace Rominas\Voting\DataTransferObjects;

use Rominas\Editions\Model\Edition;
use Rominas\Voting\Enums\VotingState;

/**
 * The public voting status: the state plus the active edition it was derived from (null when none).
 */
final class VotingStatusData
{
    public function __construct(
        public readonly VotingState $state,
        public readonly ?Edition $edition,
    ) {}
}
