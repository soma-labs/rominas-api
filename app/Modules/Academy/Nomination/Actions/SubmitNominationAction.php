<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Categories\Model\Category;

/**
 * Finalizes a member's ballot. Guards: nominations must be open and the ballot still a draft. Every
 * category of the open edition must carry exactly 5 ranked nominees, else a 422 lists the categories
 * still incomplete. On success the ballot is locked (`submitted`).
 */
class SubmitNominationAction
{
    private const int REQUIRED_PER_CATEGORY = 5;

    public function __construct(
        private readonly ResolveOpenNominationEditionAction $resolveOpenEdition,
    ) {}

    public function execute(Member $member): Nomination
    {
        $edition = $this->resolveOpenEdition->execute();

        $nomination = Nomination::query()->forMember($member)->forEdition($edition)->first();

        if ($nomination === null) {
            throw ValidationException::withMessages([
                'nominations' => __('You have not saved any nominations yet.'),
            ]);
        }

        if ($nomination->status === NominationStatus::Submitted) {
            throw ValidationException::withMessages([
                'nominations' => __('Your nominations have already been submitted.'),
            ]);
        }

        $counts = $nomination->rankings()
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $incomplete = Category::query()
            ->filterByEditionId($edition->id)
            ->get()
            ->filter(fn(Category $category): bool => (int) $counts->get($category->id, 0) !== self::REQUIRED_PER_CATEGORY)
            ->pluck('name')
            ->values();

        if ($incomplete->isNotEmpty()) {
            throw ValidationException::withMessages([
                'nominations' => __('Every category must have exactly 5 ranked nominees. Incomplete: :categories.', [
                    'categories' => $incomplete->implode(', '),
                ]),
            ]);
        }

        $nomination->status = NominationStatus::Submitted;
        $nomination->submitted_at = now();
        $nomination->save();

        return $nomination;
    }
}
