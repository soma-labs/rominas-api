<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Rominas\Categories\Enums\CategoryKind;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Voting\Enums\BallotStatus;
use Rominas\Voting\Model\BallotRanking;

/**
 * How many people voted in each category, per day, for an edition. One vote = one distinct submitted,
 * still-valid ballot participating in a category (a ballot ranks every shortlisted nominee, so the
 * distinct ballot count — not the ranking-row count — is the number of voters). Fraud-cancelled ballots
 * (those in an invalidation batch) and unsubmitted links are excluded. Days are bucketed on the stored
 * UTC `submitted_at`, matching the app timezone.
 */
final class VotesPerCategoryPerDayReport implements ReportInterface
{
    public function key(): string
    {
        return 'votes-per-category-per-day';
    }

    public function title(): string
    {
        return 'Votes per Category per Day';
    }

    public function columns(bool $includeResultsData = false): array
    {
        return ['category', 'date', 'votes'];
    }

    public function categoryFilterKinds(): array
    {
        return [CategoryKind::Ranked];
    }

    public function rows(ReportParameters $parameters): iterable
    {
        $query = BallotRanking::query()
            ->join('ballots', 'ballots.id', '=', 'ballot_rankings.ballot_id')
            ->join('categories', 'categories.id', '=', 'ballot_rankings.category_id')
            ->where('ballots.edition_id', '=', $parameters->edition->id)
            ->where('ballots.status', '=', BallotStatus::Submitted->value)
            ->whereNull('ballots.invalidation_batch_id');

        if ($parameters->category !== null) {
            $query->where('ballot_rankings.category_id', '=', $parameters->category->id);
        }

        if ($parameters->from !== null) {
            $query->where('ballots.submitted_at', '>=', $parameters->from);
        }

        if ($parameters->to !== null) {
            $query->where('ballots.submitted_at', '<=', $parameters->to);
        }

        return $query
            ->groupBy('categories.id', 'categories.name', 'date')
            ->orderBy('date')
            ->orderBy('categories.name')
            ->selectRaw(
                'categories.name as category, DATE(ballots.submitted_at) as date, '
                . 'COUNT(DISTINCT ballot_rankings.ballot_id) as votes',
            )
            ->toBase()
            ->get()
            ->map(fn(object $row): array => [
                'category' => (string) $row->category,
                'date' => (string) $row->date,
                'votes' => (int) $row->votes,
            ])
            ->all();
    }
}
