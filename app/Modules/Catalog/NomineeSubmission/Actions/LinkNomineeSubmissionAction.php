<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Users\Model\User;

/**
 * Resolves a pending submission to an existing canonical Catalog entity: marks it resolved and backfills
 * `nominee_id` on every ranking that pointed at it, so scoring and shortlisting (which tally by
 * nominee_id) fold all the spellings of that name onto one nominee. Also the final step of
 * {@see CreateNomineeFromSubmissionAction} once a new entity has been created.
 *
 * Guards the per-ballot invariant: two different typed names that turn out to be the same act would put
 * one nominee twice on a member's category — a genuine data-quality call, so it is refused (422) with the
 * affected ballots named rather than silently double-counted.
 */
class LinkNomineeSubmissionAction
{
    public function execute(NomineeSubmission $submission, int $nomineeId, User $reviewer, ?string $note = null): NomineeSubmission
    {
        if ($submission->status !== NomineeSubmissionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending submission can be reconciled.',
            ]);
        }

        $modelClass = $submission->nominee_type->modelClass();

        if (! $modelClass::query()->whereKey($nomineeId)->exists()) {
            throw ValidationException::withMessages([
                'nominee_id' => 'The selected nominee does not exist for this type.',
            ]);
        }

        $this->assertNoBallotCollision($submission, $nomineeId);

        return DB::transaction(function () use ($submission, $nomineeId, $reviewer, $note): NomineeSubmission {
            $submission->forceFill([
                'status' => NomineeSubmissionStatus::Resolved,
                'resolved_nominee_id' => $nomineeId,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            NominationRanking::query()
                ->where('nominee_submission_id', '=', $submission->id)
                ->update(['nominee_id' => $nomineeId]);

            return $submission;
        });
    }

    /**
     * Refuse if backfilling would place `$nomineeId` twice in the same ballot + category (another already
     * resolved pick there is the same entity).
     *
     * @throws ValidationException
     */
    private function assertNoBallotCollision(NomineeSubmission $submission, int $nomineeId): void
    {
        $pairs = NominationRanking::query()
            ->where('nominee_submission_id', '=', $submission->id)
            ->get(['nomination_id', 'category_id'])
            ->unique(fn(NominationRanking $ranking): string => $ranking->nomination_id . ':' . $ranking->category_id);

        foreach ($pairs as $pair) {
            $collides = NominationRanking::query()
                ->where('nomination_id', '=', $pair->nomination_id)
                ->where('category_id', '=', $pair->category_id)
                ->where('nominee_type', '=', $submission->nominee_type->value)
                ->where('nominee_id', '=', $nomineeId)
                ->exists();

            if ($collides) {
                throw ValidationException::withMessages([
                    'nominee_id' => 'Linking this name would list the same nominee twice on at least one '
                        . 'ballot. Reconcile the conflicting submission first or reject one of them.',
                ]);
            }
        }
    }
}
