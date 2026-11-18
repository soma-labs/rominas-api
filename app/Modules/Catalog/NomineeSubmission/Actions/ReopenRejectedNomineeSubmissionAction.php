<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Editions\Enums\EditionStatus;

/**
 * Undoes a mistaken rejection: puts a rejected submission back to `pending`, so the admin can review it
 * again (link, create or reject). Rejection never touched the rankings (they kept `nominee_id = null`),
 * so only the submission's own review fields are reset; a later link backfills the rankings as usual.
 * The earlier rejection — who, when and the reason — stays in the audit trail.
 *
 * Only allowed before voting opens, like {@see UnlinkNomineeSubmissionAction}: from then on the shortlist
 * is locked. A shortlist generated earlier is left alone — the pending submission blocks regeneration
 * until it is reconciled.
 */
class ReopenRejectedNomineeSubmissionAction
{
    public function execute(NomineeSubmission $submission): NomineeSubmission
    {
        if ($submission->status !== NomineeSubmissionStatus::Rejected) {
            throw ValidationException::withMessages([
                'status' => __('Only a rejected submission can be marked as pending.'),
            ]);
        }

        $editionStatus = $submission->edition->status;

        if ($editionStatus !== EditionStatus::NominationsOpen && $editionStatus !== EditionStatus::NominationsClosed) {
            throw ValidationException::withMessages([
                'status' => __('Submissions can only be marked as pending before voting opens.'),
            ]);
        }

        $submission->forceFill([
            'status' => NomineeSubmissionStatus::Pending,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ])->save();

        return $submission;
    }
}
