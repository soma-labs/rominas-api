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
use Rominas\Catalog\NomineeSubmission\Support\NomineeNameNormalizer;
use Rominas\Categories\Model\Category;

/**
 * Saves (replaces) a member's ranked picks for one category — the save/resume unit. A pick is either:
 *
 * - a Catalog entry the member chose from the suggestions: the ranking carries its `nominee_id` at once
 *   and has no submission, so there is nothing for an admin to reconcile;
 * - a typed name (free text): staged as a NomineeSubmission (deduplicated per edition/type) for later
 *   admin reconciliation. The ranking points at that submission and carries its resolved Catalog id
 *   (null while pending, pre-filled if the name was reconciled earlier).
 *
 * Guards: nominations must be open, the ballot must still be a draft, the category must belong to the open
 * edition, a chosen Catalog entry must exist for the category's type, and no two picks may be the same
 * nominee — the same Catalog entry twice, two typed names that normalize alike, or a typed name that is
 * (or normalizes to the name of) a chosen Catalog entry.
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
                'category' => __('This category does not belong to the open edition.'),
            ]);
        }

        $nomination = Nomination::query()->forMember($member)->forEdition($edition)->first();

        if ($nomination !== null && $nomination->status === NominationStatus::Submitted) {
            throw ValidationException::withMessages([
                'nominations' => __('Your nominations have already been submitted.'),
            ]);
        }

        return DB::transaction(function () use ($member, $edition, $category, $data, $nomination): Nomination {
            $nomination ??= Nomination::create([
                'member_id' => $member->id,
                'edition_id' => $edition->id,
                'status' => NominationStatus::Draft,
            ]);

            $nomination->rankings()->where('category_id', $category->id)->delete();

            /** @var array<int, true> $seenSubmissions */
            $seenSubmissions = [];
            /** @var array<int, true> $seenNominees */
            $seenNominees = [];
            /** @var array<string, true> $typedNames */
            $typedNames = [];
            /** @var array<string, true> $chosenNames */
            $chosenNames = [];

            foreach ($data->nominees as $index => $pick) {
                $submission = null;
                $nomineeId = $pick->nomineeId;

                if ($nomineeId !== null) {
                    $chosenNames[$this->chosenEntryName($category, $nomineeId, $index)] = true;
                } else {
                    $submission = $this->resolveSubmission->execute($edition, $category->rankedNomineeType(), $pick->name);
                    $nomineeId = $submission->resolved_nominee_id;
                    $typedNames[$submission->normalized_name] = true;

                    if (isset($seenSubmissions[$submission->id])) {
                        $this->failAsDuplicate();
                    }

                    $seenSubmissions[$submission->id] = true;
                }

                if ($nomineeId !== null) {
                    if (isset($seenNominees[$nomineeId])) {
                        $this->failAsDuplicate();
                    }

                    $seenNominees[$nomineeId] = true;
                }

                $nomination->rankings()->create([
                    'category_id' => $category->id,
                    'rank' => $index + 1,
                    'nominee_submission_id' => $submission?->id,
                    'nominee_type' => $category->rankedNomineeType(),
                    'nominee_id' => $nomineeId,
                ]);
            }

            // A typed name that is a chosen entry's own name would later be reconciled onto that entry,
            // listing it twice on this ballot.
            if (array_intersect_key($typedNames, $chosenNames) !== []) {
                $this->failAsDuplicate();
            }

            return $nomination->load('rankings');
        });
    }

    /**
     * The normalized name of the Catalog entry the member chose, or a 422 on that pick when there is no
     * such entry for the category's nominee type.
     *
     * @throws ValidationException
     */
    private function chosenEntryName(Category $category, int $nomineeId, int $index): string
    {
        $modelClass = $category->rankedNomineeType()->modelClass();

        $name = $modelClass::query()->whereKey($nomineeId)->toBase()->value('name');

        if ($name === null) {
            throw ValidationException::withMessages([
                "nominees.{$index}.nominee_id" => __('The selected nominee no longer exists. Please choose again.'),
            ]);
        }

        return NomineeNameNormalizer::normalize((string) $name);
    }

    /**
     * @throws ValidationException
     */
    private function failAsDuplicate(): never
    {
        throw ValidationException::withMessages([
            'nominees' => __('Two of your picks refer to the same nominee.'),
        ]);
    }
}
