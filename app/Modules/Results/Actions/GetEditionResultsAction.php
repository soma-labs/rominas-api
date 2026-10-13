<?php

declare(strict_types=1);

namespace Rominas\Results\Actions;

use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\Results\DataTransferObjects\EditionResults;
use Rominas\Results\Enums\ResultsSource;
use Rominas\Results\Model\ResultEntry;
use Rominas\Results\Model\ResultSnapshot;
use Rominas\Results\Model\SpecialResultEntry;
use Rominas\Scoring\Actions\ComputeEditionScoresAction;
use Rominas\Scoring\DataTransferObjects\CategoryScore;
use Rominas\Scoring\DataTransferObjects\EditionScore;
use Rominas\Scoring\DataTransferObjects\NomineeScore;
use Rominas\SpecialAwards\Actions\ComputeSpecialResultsAction;
use Rominas\SpecialAwards\DataTransferObjects\SpecialCategoryResult;
use Rominas\SpecialAwards\DataTransferObjects\SpecialEntryResult;

/**
 * The single read path for an edition's results: once the edition's results are published a frozen
 * {@see ResultSnapshot} exists and is rebuilt into the DTO trees; otherwise (during the custodian review
 * window) the ranked categories are scored live by Scoring and the special ones tallied live from the
 * members' votes. Both paths yield the same shapes, wrapped in {@see EditionResults} with the source and
 * publication time, so callers render one JSON structure regardless of publication state.
 */
class GetEditionResultsAction
{
    public function __construct(
        private readonly ComputeEditionScoresAction $compute,
        private readonly ComputeSpecialResultsAction $computeSpecial,
    ) {}

    public function execute(Edition $edition): EditionResults
    {
        $snapshot = ResultSnapshot::query()
            ->forEdition($edition)
            ->with(['entries', 'specialEntries'])
            ->first();

        if ($snapshot !== null) {
            return new EditionResults(
                edition: $edition,
                score: $this->fromSnapshot($edition, $snapshot),
                specialCategories: $this->specialFromSnapshot($edition, $snapshot),
                source: ResultsSource::Snapshot,
                publishedAt: $snapshot->published_at,
            );
        }

        // Scoring enforces the "voting closed" gate, so it runs first: nothing is tallied before it.
        $score = $this->compute->execute($edition);

        return new EditionResults(
            edition: $edition,
            score: $score,
            specialCategories: $this->computeSpecial->execute($edition),
            source: ResultsSource::Live,
            publishedAt: null,
        );
    }

    private function fromSnapshot(Edition $edition, ResultSnapshot $snapshot): EditionScore
    {
        $entriesByCategory = $snapshot->entries->groupBy('category_id');

        $categories = Category::query()
            ->filterByEditionId($edition->id)
            ->ranked()
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

        return new EditionScore($edition->id, $categoryScores, $snapshot->algorithm);
    }

    /**
     * @return list<SpecialCategoryResult>
     */
    private function specialFromSnapshot(Edition $edition, ResultSnapshot $snapshot): array
    {
        $entriesByCategory = $snapshot->specialEntries->groupBy('category_id');

        $categories = Category::query()
            ->filterByEditionId($edition->id)
            ->special()
            ->get()
            ->sortBy('position')
            ->values();

        $results = [];

        foreach ($categories as $category) {
            /** @var \Illuminate\Support\Collection<int, SpecialResultEntry> $rows */
            $rows = ($entriesByCategory->get($category->id) ?? collect())
                ->sortBy([['position', 'asc'], ['id', 'asc']])
                ->values();

            $entries = $rows
                ->map(static fn(SpecialResultEntry $entry): SpecialEntryResult => new SpecialEntryResult(
                    title: $entry->title,
                    votes: $entry->votes,
                    position: $entry->position,
                ))
                ->all();

            $results[] = new SpecialCategoryResult($category->id, $entries);
        }

        return $results;
    }
}
