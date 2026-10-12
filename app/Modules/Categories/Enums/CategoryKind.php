<?php

declare(strict_types=1);

namespace Rominas\Categories\Enums;

/**
 * How an award category is decided.
 *
 * - `Ranked`: the normal flow — members rank Catalog nominees, a shortlist goes to the public vote and
 *   the result is scored.
 * - `ProposeOrVote`: a member either proposes one free-text entry or votes for one approved entry; the
 *   entry with the most member votes wins. No shortlist, no public vote.
 * - `Appointed`: the result comes from outside (e.g. the critics' committee) and an admin enters it.
 *   Members and voters never see the category before the results are published.
 */
enum CategoryKind: string
{
    case Ranked = 'ranked';
    case ProposeOrVote = 'propose_or_vote';
    case Appointed = 'appointed';

    public function label(): string
    {
        return match ($this) {
            self::Ranked => 'Ranked',
            self::ProposeOrVote => 'Propose or vote',
            self::Appointed => 'Appointed',
        };
    }
}
