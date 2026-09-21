<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Enums;

/**
 * Reconciliation state of a free-text nominee a member typed on the ballot. `pending` awaits an admin's
 * link-or-create decision; `resolved` has been linked to a canonical Catalog entity (`resolved_nominee_id`);
 * `rejected` was discarded (junk/spam) and its rankings stay unresolved.
 */
enum NomineeSubmissionStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
        };
    }
}
