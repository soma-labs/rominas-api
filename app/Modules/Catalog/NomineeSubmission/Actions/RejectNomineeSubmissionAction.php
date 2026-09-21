<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Users\Model\User;

/**
 * Discards a pending submission (junk/spam typed name). Its rankings stay unresolved — a fully-rejected
 * pick leaves that ballot's category short of five nominees, which the shortlist-generation guard surfaces
 * so an admin can follow up. Rejection does not touch the Catalog.
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
