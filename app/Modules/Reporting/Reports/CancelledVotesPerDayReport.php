<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Voting\Model\Ballot;

/**
 * How many public ballots were cancelled (fraud-invalidated) per day, for an edition. Counts submitted
 * ballots that belong to an invalidation batch, bucketed on the UTC `submitted_at` (when the vote was
 * cast) so it lines up with the votes-per-day report. Optionally narrowed to a `submitted_at` window.
 */
final class CancelledVotesPerDayReport implements ReportInterface
{
    public function key(): string
    {
        return 'cancelled-votes-per-day';
    }

    public function title(): string
    {
        return 'Cancelled Votes per Day';
    }

    public function columns(bool $includeResultsData = false): array
    {
        return ['date', 'cancelled'];
    }

    public function rows(ReportParameters $parameters): iterable
    {
        $query = Ballot::query()
            ->forEdition($parameters->edition)
            ->submitted()
            ->invalidated();

        if ($parameters->from !== null) {
            $query->where('submitted_at', '>=', $parameters->from);
        }

        if ($parameters->to !== null) {
            $query->where('submitted_at', '<=', $parameters->to);
        }

        return $query
            ->groupBy('date')
            ->orderBy('date')
            ->selectRaw('DATE(submitted_at) as date, COUNT(*) as cancelled')
            ->toBase()
            ->get()
            ->map(fn(object $row): array => [
                'date' => (string) $row->date,
                'cancelled' => (int) $row->cancelled,
            ])
            ->all();
    }
}
