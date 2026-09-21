<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Nomination\QueryBuilders\NominationQueryBuilder;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Scoring\RankPoints;

/**
 * Generates (or regenerates) one category's public-voting shortlist from the submitted academy ballots.
 * Every submitted ranking for the category is scored with the {@see RankPoints} curve and summed per
 * nominee; the top {@see RankPoints::RANKS} advance (ties broken deterministically by nominee id, then
 * settled manually by admins via the review/adjust flow). Regeneration replaces the category's entries.
 *
 * Guard: only while the edition is `nominations_closed` — nominations are frozen but public voting has
 * not opened. Once voting opens the shortlist is locked (a later status fails this guard).
 */
class GenerateCategoryShortlistAction
{
    /**
     * @return Collection<int, ShortlistEntry>
     */
    public function execute(Edition $edition, Category $category): Collection
    {
        $this->assertGeneratable($edition);

        if ($category->edition_id !== $edition->id) {
            throw ValidationException::withMessages([
                'category' => 'The category does not belong to this edition.',
            ]);
        }

        $totals = $this->tallyPoints($edition, $category);

        return DB::transaction(function () use ($edition, $category, $totals): Collection {
            ShortlistEntry::query()
                ->forEdition($edition)
                ->forCategory($category)
                ->delete();

            $entries = new Collection();
            $position = 1;

            foreach ($totals as $total) {
                $entries->push(ShortlistEntry::query()->create([
                    'edition_id' => $edition->id,
                    'category_id' => $category->id,
                    'nominee_type' => $total['nominee_type'],
                    'nominee_id' => $total['nominee_id'],
                    'points' => $total['points'],
                    'position' => $position++,
                ]));
            }

            return $entries;
        });
    }

    /**
     * @throws ValidationException
     */
    public function assertGeneratable(Edition $edition): void
    {
        if ($edition->status !== EditionStatus::NominationsClosed) {
            throw ValidationException::withMessages([
                'status' => 'Shortlists can only be generated while nominations are closed and before voting opens.',
            ]);
        }

        // Free-text nominees must all be reconciled to canonical Catalog entities first — an unresolved
        // name would otherwise be silently dropped from the tally, splitting or losing that nominee's points.
        $pending = NomineeSubmission::query()->forEdition($edition)->pending()->count();

        if ($pending > 0) {
            throw ValidationException::withMessages([
                'nominee_submissions' => "{$pending} free-text nomination(s) still need reconciliation before shortlists can be generated.",
            ]);
        }
    }

    /**
     * Sum academy points per nominee across every submitted ballot for the category, ordered points
     * desc then nominee id asc, capped at the shortlist size.
     *
     * @return list<array{nominee_type: NomineeType, nominee_id: int, points: int}>
     */
    private function tallyPoints(Edition $edition, Category $category): array
    {
        $rankings = NominationRanking::query()
            ->whereHas('nomination', fn(NominationQueryBuilder $query) => $query->forEdition($edition)->submitted())
            ->where('category_id', '=', $category->id)
            ->whereNotNull('nominee_id')
            ->get(['nominee_type', 'nominee_id', 'rank']);

        /** @var array<string, array{nominee_type: NomineeType, nominee_id: int, points: int}> $totals */
        $totals = [];

        foreach ($rankings as $ranking) {
            $key = $ranking->nominee_type->value . ':' . $ranking->nominee_id;

            if (! isset($totals[$key])) {
                $totals[$key] = [
                    'nominee_type' => $ranking->nominee_type,
                    'nominee_id' => (int) $ranking->nominee_id,
                    'points' => 0,
                ];
            }

            $totals[$key]['points'] += RankPoints::forRank($ranking->rank);
        }

        $ordered = array_values($totals);

        // Order points desc, then nominee id asc as a deterministic tie-break. The array spaceship
        // compares element by element, so index 0 is the primary key and index 1 only breaks ties;
        // `$b <=> $a` on the points reverses that key to descending, while the ids stay ascending.
        usort(
            $ordered,
            static fn(array $a, array $b): int => [$b['points'], $a['nominee_id']] <=> [$a['points'], $b['nominee_id']],
        );

        return array_slice($ordered, 0, RankPoints::RANKS);
    }
}
