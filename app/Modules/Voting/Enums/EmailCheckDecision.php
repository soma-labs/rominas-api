<?php

declare(strict_types=1);

namespace Rominas\Voting\Enums;

/**
 * What a voting-link request does with an address once it has been validated. `accepted` — the link is
 * sent; `accepted_with_note` — the link is sent and the ballot is marked for fraud review; `rejected` —
 * no link is issued (so this value never reaches a ballot row).
 */
enum EmailCheckDecision: string
{
    case Accepted = 'accepted';
    case AcceptedWithNote = 'accepted_with_note';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::AcceptedWithNote => 'Accepted with a note',
            self::Rejected => 'Rejected',
        };
    }
}
