<?php

declare(strict_types=1);

namespace Rominas\Results\Actions;

use Rominas\Editions\Model\Edition;
use Rominas\Results\Model\ResultEntry;
use Rominas\Results\Model\ResultSnapshot;
use Rominas\Scoring\Actions\ComputeEditionScoresAction;
use Rominas\Scoring\DataTransferObjects\CategoryScore;
use Rominas\Scoring\DataTransferObjects\EditionScore;
use Rominas\Scoring\DataTransferObjects\NomineeScore;

/**
 * The single read path for an edition's results: once the edition's results are published a frozen
 * {@see ResultSnapshot} exists and is rebuilt into the Scoring DTO tree; otherwise (during the
 * custodian review window) the scores are computed live from Scoring. Both paths yield the same
 * {@see EditionScore} shape, so callers render one JSON structure regardless of publication state.
 */
class GetEditionResultsAction
{
    public function __construct(
        private readonly ComputeEditionScoresAction $compute,
    ) {}

    public function execute(Edition $edition): EditionScore
    {
        $snapshot = ResultSnapshot::query()
            ->forEdition($edition)
            ->with('entries')
            ->first();

        if ($snapshot !== null) {
            return $this->fromSnapshot($edition, $snapshot);
        }

        return $this->compute->execute($edition);
    }

    private function fromSnapshot(Edition $edition, ResultSnapshot $snapshot): EditionScore
    {
        $entriesByCategory = $snapshot->entries->groupBy('category_id');

        $categories = $edition->categories()
            ->get()
            ->sortBy('position')
            ->values();

        $categoryScores = [];

        foreach ($categories as $category) {
            /** @var \Illuminate\Support\Collection<int, ResultEntry> $rows */
            $rows = ($entriesByCategory->get($category->id) ?? collect())
                ->sortBy('position')
                ->values();

            $nominees = $rows
                ->map(static fn(ResultEntry $entry): NomineeScore => new NomineeScore(
                    nomineeType: $entry->nominee_type,
                    nomineeId: (int) $entry->nominee_id,
                    academyPoints: $entry->academy_points,
                    publicPoints: $entry->public_points,
                    academyShare: $entry->academy_share,
                    publicShare: $entry->public_share,
                    finalScore: $entry->final_score,
                    position: $entry->position,
                ))
                ->all();

            $categoryScores[] = new CategoryScore($category->id, $nominees);
        }

        return new EditionScore($edition->id, $categoryScores);
    }
}
