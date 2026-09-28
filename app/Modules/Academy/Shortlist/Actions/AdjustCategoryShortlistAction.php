<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Academy\Shortlist\Support\AcademyPointTally;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Scoring\RankPoints;

/**
 * Replaces a category's shortlist with an admin-curated final list — the manual review/adjust flow. The
 * admin reorders/deletes from the ranked candidate pool (see {@see GetCategoryShortlistCandidatesAction})
 * and submits the final ordered nominees; this overwrites the category's entries with exactly that set,
 * position following submission order. Each nominee's `points` are re-derived from the academy tally
 * (0 if the admin added one with no academy nominations) for display; Scoring recomputes independently.
 *
 * Guard: only while the edition is `nominations_closed` (same window as generation) — the shortlist is
 * frozen once voting opens.
 */
class AdjustCategoryShortlistAction
{
    public function __construct(
        private readonly AcademyPointTally $tally,
    ) {}

    /**
     * @param  list<int>  $nomineeIds  the final shortlist, in order (index 0 → position 1)
     * @return Collection<int, ShortlistEntry>
     */
    public function execute(Edition $edition, Category $category, array $nomineeIds): Collection
    {
        $this->assertAdjustable($edition);

        if ($category->edition_id !== $edition->id) {
            throw ValidationException::withMessages([
                'category' => __('The category does not belong to this edition.'),
            ]);
        }

        if (count($nomineeIds) > RankPoints::RANKS) {
            throw ValidationException::withMessages([
                'nominees' => __('A shortlist may hold at most :count nominees.', ['count' => RankPoints::RANKS]),
            ]);
        }

        $this->assertNomineesExist($category, $nomineeIds);

        $points = $this->tally->perNominee($edition, $category);

        return DB::transaction(function () use ($edition, $category, $nomineeIds, $points): Collection {
            ShortlistEntry::query()
                ->forEdition($edition)
                ->forCategory($category)
                ->delete();

            $entries = new Collection();
            $position = 1;

            foreach ($nomineeIds as $nomineeId) {
                $entries->push(ShortlistEntry::query()->create([
                    'edition_id' => $edition->id,
                    'category_id' => $category->id,
                    'nominee_type' => $category->nominee_type,
                    'nominee_id' => $nomineeId,
                    'points' => $points[$nomineeId] ?? 0,
                    'position' => $position++,
                ]));
            }

            return $entries;
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertAdjustable(Edition $edition): void
    {
        if ($edition->status !== EditionStatus::NominationsClosed) {
            throw ValidationException::withMessages([
                'status' => __('The shortlist can only be adjusted while nominations are closed and before voting opens.'),
            ]);
        }
    }

    /**
     * Every submitted id must be a real nominee of the category's type.
     *
     * @param  list<int>  $nomineeIds
     *
     * @throws ValidationException
     */
    private function assertNomineesExist(Category $category, array $nomineeIds): void
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $category->nominee_type->modelClass();

        $existing = $modelClass::query()
            ->whereIn('id', $nomineeIds)
            ->pluck('id')
            ->map(static fn($id): int => (int) $id)
            ->all();

        if (array_diff($nomineeIds, $existing) !== []) {
            throw ValidationException::withMessages([
                'nominees' => __('One or more nominees do not exist for this category.'),
            ]);
        }
    }
}
