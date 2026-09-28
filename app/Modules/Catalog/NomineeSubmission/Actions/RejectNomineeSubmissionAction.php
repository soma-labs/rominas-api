<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Users\Model\User;

/**
 * Discards a pending submission (junk/spam typed name). Its rankings stay unresolved — permanently
 * excluded from every tally that reads `NominationRankingQueryBuilder::resolved()` (shortlist generation,
 * Scoring, Reporting) — and are surfaced instead as `rejected_picks` per category in the shortlist
 * listing ({@see \Rominas\Academy\Shortlist\Actions\CountRejectedPicksAction}), so a rejected pick doesn't
 * just silently vanish from the count. Rejection does not touch the Catalog.
 */
class RejectNomineeSubmissionAction
{
    public function execute(NomineeSubmission $submission, User $reviewer, ?string $note = null): NomineeSubmission
    {
        if ($submission->status !== NomineeSubmissionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending submission can be rejected.',
            ]);
        }

        $submission->forceFill([
            'status' => NomineeSubmissionStatus::Rejected,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        return $submission;
    }
}
