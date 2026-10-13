<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\Model\Member;
use Rominas\Catalog\NomineeSubmission\Support\NomineeNameNormalizer;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Actions\ResolveOpenSpecialCategoryAction;
use Rominas\SpecialAwards\Entry\DataTransferObjects\SpecialEntryData;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;

/**
 * Records a member's proposal of a new entry in a propose-or-vote category. The entry starts pending —
 * other members see it only after an admin approves it — and it carries the proposer's vote from the
 * start, so a member who proposes has used their one action in the category.
 *
 * Guards: the category must be open for members, the member must not have proposed or voted in it yet,
 * and the title must not be one that is already approved (the member can vote for that entry instead).
 */
class ProposeSpecialEntryAction
{
    public function __construct(
        private readonly ResolveOpenSpecialCategoryAction $resolveOpenCategory,
    ) {}

    public function execute(Member $member, Category $category, SpecialEntryData $data): SpecialEntry
    {
        $this->resolveOpenCategory->execute($category);

        if (SpecialVote::query()->forMember($member)->forCategory($category)->exists()) {
            $this->failAsAlreadyActed();
        }

        $alreadyApproved = SpecialEntry::query()
            ->forCategory($category)
            ->approved()
            ->where('normalized_title', '=', NomineeNameNormalizer::normalize($data->title))
            ->exists();

        if ($alreadyApproved) {
            throw ValidationException::withMessages([
                'title' => __('This entry already exists. You can vote for it instead.'),
            ]);
        }

        try {
            return DB::transaction(function () use ($member, $category, $data): SpecialEntry {
                $entry = SpecialEntry::create([
                    'category_id' => $category->id,
                    'title' => $data->title,
                    'status' => SpecialEntryStatus::Pending,
                    'proposed_by_member_id' => $member->id,
                ]);

                SpecialVote::create([
                    'category_id' => $category->id,
                    'member_id' => $member->id,
                    'special_entry_id' => $entry->id,
                ]);

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests from the same member at once: the unique key let only one through.
            $this->failAsAlreadyActed();
        }
    }

    /**
     * @throws ValidationException
     */
    private function failAsAlreadyActed(): never
    {
        throw ValidationException::withMessages([
            'category' => __('You have already proposed or voted in this category.'),
        ]);
    }
}
