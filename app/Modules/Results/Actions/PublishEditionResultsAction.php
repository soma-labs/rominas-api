<?php

declare(strict_types=1);

namespace Rominas\Results\Actions;

use Illuminate\Support\Facades\DB;
use Rominas\Editions\Model\Edition;
use Rominas\Results\Model\ResultEntry;
use Rominas\Results\Model\ResultSnapshot;
use Rominas\Results\Model\SpecialResultEntry;
use Rominas\Scoring\Actions\ComputeEditionScoresAction;
use Rominas\SpecialAwards\Actions\ComputeSpecialResultsAction;

/**
 * Freezes an edition's final results into an immutable {@see ResultSnapshot}. Called by the
 * FreezeResultsOnResultsPublished listener when the edition transitions to `results_published`.
 *
 * Recomputes the scores fresh (bypassing Scoring's cache) with the just-published status and tallies the
 * special categories, then persists both as snapshot + entry rows, capturing the edition's vote weights.
 * Idempotent: an existing snapshot for the edition is replaced (its entries cascade away), so a re-run is
 * safe.
 */
class PublishEditionResultsAction
{
    public function __construct(
        private readonly ComputeEditionScoresAction $compute,
        private readonly ComputeSpecialResultsAction $computeSpecial,
    ) {}

    public function execute(Edition $edition): ResultSnapshot
    {
        $score = $this->compute->execute($edition, fresh: true);
        $specialCategories = $this->computeSpecial->execute($edition);

        return DB::transaction(function () use ($edition, $score, $specialCategories): ResultSnapshot {
            ResultSnapshot::query()->forEdition($edition)->delete();

            $snapshot = ResultSnapshot::query()->create([
                'edition_id' => $edition->id,
                'academy_vote_weight' => $edition->academy_vote_weight,
                'public_vote_weight' => $edition->public_vote_weight,
                'algorithm' => $score->algorithm,
                'published_at' => now(),
            ]);

            foreach ($score->categories as $category) {
                foreach ($category->nominees as $nominee) {
                    ResultEntry::query()->create([
                        'result_snapshot_id' => $snapshot->id,
                        'category_id' => $category->categoryId,
                        'nominee_type' => $nominee->nomineeType,
                        'nominee_id' => $nominee->nomineeId,
                        'academy_points' => $nominee->academyPoints,
                        'public_points' => $nominee->publicPoints,
                        'academy_share' => $nominee->academyShare,
                        'public_share' => $nominee->publicShare,
                        'final_score' => $nominee->finalScore,
                        'position' => $nominee->position,
                    ]);
                }
            }

            foreach ($specialCategories as $category) {
                foreach ($category->entries as $entry) {
                    SpecialResultEntry::query()->create([
                        'result_snapshot_id' => $snapshot->id,
                        'category_id' => $category->categoryId,
                        'title' => $entry->title,
                        'description' => $entry->description,
                        'votes' => $entry->votes,
                        'position' => $entry->position,
                    ]);
                }
            }

            return $snapshot->load(['entries', 'specialEntries']);
        });
    }
}
