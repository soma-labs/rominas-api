<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Enums;

/**
 * Review state of an entry in a special category. `pending` awaits admin review and only its proposer
 * sees it; `approved` is in the pool members vote from; `rejected` was declined; `merged` was a duplicate
 * folded into another entry, which took over its votes.
 */
enum SpecialEntryStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Merged = 'merged';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Merged => 'Merged',
        };
    }
}
