<?php

declare(strict_types=1);

namespace Rominas\Voting\Enums;

use Carbon\CarbonInterface;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

/**
 * Where public voting stands for the active edition. Voting is `open` only when the edition's status is
 * `voting_open` AND now falls within `[voting_start_at, voting_end_at]`. Status and dates are checked
 * separately (an admin may move the edition to `voting_open` early, or leave it there after the end date),
 * so a `voting_open` edition can still be `upcoming` or `closed` by its dates.
 */
enum VotingState: string
{
    /** No active edition. */
    case None = 'none';
    /** Voting hasn't started yet: an earlier lifecycle status, or `voting_open` before the start date. */
    case Upcoming = 'upcoming';
    case Open = 'open';
    /** Voting is over: a later lifecycle status, or `voting_open` after the end date. */
    case Closed = 'closed';

    public static function of(?Edition $edition, CarbonInterface $now): self
    {
        if ($edition === null) {
            return self::None;
        }

        return match ($edition->status) {
            EditionStatus::Draft,
            EditionStatus::NominationsOpen,
            EditionStatus::NominationsClosed => self::Upcoming,
            EditionStatus::VotingOpen => match (true) {
                $now->lt($edition->voting_start_at) => self::Upcoming,
                $now->gt($edition->voting_end_at) => self::Closed,
                default => self::Open,
            },
            default => self::Closed,
        };
    }
}
