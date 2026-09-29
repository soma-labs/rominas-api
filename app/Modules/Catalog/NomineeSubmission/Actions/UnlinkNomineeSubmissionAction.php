<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Editions\Enums\EditionStatus;

/**
 * Undoes a mistaken link: reverts a resolved submission to `pending` and clears the `nominee_id` that
 * {@see LinkNomineeSubmissionAction} backfilled on its rankings, so the typed name can be reconciled again.
 * The Catalog entity it pointed at is left in place (it may have been created by "create & link" and can be
 * removed through Catalog CRUD); the audit trail keeps who unlinked what.
 *
 * Only allowed before voting opens: from then on the shortlist is locked, and moving academy points would
 * silently shift the results. A shortlist generated earlier is left alone — the submission being pending
 * again blocks regeneration until it is reconciled.
 */
class UnlinkNomineeSubmissionAction
{
    public function execute(NomineeSubmission $submission): NomineeSubmission
    {
        if ($submission->status !== NomineeSubmissionStatus::Resolved) {
            throw ValidationException::withMessages([
                'status' => __('Only a linked submission can be unlinked.'),
            ]);
        }

        $editionStatus = $submission->edition->status;

        if ($editionStatus !== EditionStatus::NominationsOpen && $editionStatus !== EditionStatus::NominationsClosed) {
            throw ValidationException::withMessages([
                'status' => __('Submissions can only be unlinked before voting opens.'),
            ]);
        }

        return DB::transaction(function () use ($submission): NomineeSubmission {
            $submission->forceFill([
                'status' => NomineeSubmissionStatus::Pending,
                'resolved_nominee_id' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ])->save();

            NominationRanking::query()
                ->where('nominee_submission_id', '=', $submission->id)
                ->update(['nominee_id' => null]);

            return $submission;
        });
    }
}
