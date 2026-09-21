<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\DataTransferObjects\CategoryRankingData;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Catalog\NomineeSubmission\Actions\ResolveOrCreateNomineeSubmissionAction;
use Rominas\Categories\Model\Category;

/**
 * Saves (replaces) a member's ranked picks for one category — the save/resume unit. Members type nominee
 * names (free text); each name is staged as a NomineeSubmission (deduplicated per edition/type) for
 * later admin reconciliation into a canonical Catalog entity. The ranking points at that submission and
 * carries its resolved Catalog id (null while pending, pre-filled if the name was reconciled earlier).
 *
 * Guards: nominations must be open, the ballot must still be a draft, the category must belong to the open
 * edition, and no two typed names may resolve to the same submission (an already-blocked exact duplicate,
 * or two spellings that normalize alike).
 */
class SaveCategoryRankingAction
{
    public function __construct(
        private readonly ResolveOpenNominationEditionAction $resolveOpenEdition,
        private readonly ResolveOrCreateNomineeSubmissionAction $resolveSubmission,
    ) {}

    public function execute(Member $member, Category $category, CategoryRankingData $data): Nomination
    {
        $edition = $this->resolveOpenEdition->execute();

        if ($category->edition_id !== $edition->id) {
            throw ValidationException::withMessages([
                'category' => 'This category does not belong to the open edition.',
            ]);
        }

        $nomination = Nomination::query()->forMember($member)->forEdition($edition)->first();

        if ($nomination !== null && $nomination->status === NominationStatus::Submitted) {
            throw ValidationException::withMessages([
                'nominations' => 'Your nominations have already been submitted.',
            ]);
        }

        return DB::transaction(function () use ($member, $edition, $category, $data, $nomination): Nomination {
            $nomination ??= Nomination::create([
                'member_id' => $member->id,
                'edition_id' => $edition->id,
                'status' => NominationStatus::Draft,
            ]);

            $nomination->rankings()->where('category_id', $category->id)->delete();

            /** @var array<int, true> $seen */
            $seen = [];

            foreach ($data->nomineeNames as $index => $name) {
                $submission = $this->resolveSubmission->execute($edition, $category->nominee_type, $name);

                if (isset($seen[$submission->id])) {
                    throw ValidationException::withMessages([
                        'nominees' => 'Two of your picks refer to the same nominee.',
                    ]);
                }

                $seen[$submission->id] = true;

                $nomination->rankings()->create([
                    'category_id' => $category->id,
                    'rank' => $index + 1,
                    'nominee_submission_id' => $submission->id,
                    'nominee_type' => $category->nominee_type,
                    'nominee_id' => $submission->resolved_nominee_id,
                ]);
            }

            return $nomination->load('rankings');
        });
    }
}
