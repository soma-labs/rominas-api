<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Voting\Model\Ballot;

/**
 * Headline totals for an edition, one `metric | value` row each: voting links issued, ballots submitted,
 * valid and cancelled votes, and academy nominations submitted or still in draft. Each metric with a
 * timestamp honours the optional `from`/`to` window on it (links on `created_at`, votes and submitted
 * nominations on `submitted_at`); the draft count is a point-in-time snapshot and ignores the window,
 * since a draft has no submission date.
 */
final class EditionSummaryReport implements ReportInterface
{
    public function key(): string
    {
        return 'edition-summary';
    }

    public function title(): string
    {
        return 'Edition Summary';
    }

    public function columns(): array
    {
        return ['metric', 'value'];
    }

    public function rows(ReportParameters $parameters): iterable
    {
        $edition = $parameters->edition;

        $ballots = static fn() => Ballot::query()->forEdition($edition);

        return [
            ['metric' => 'Voting links issued', 'value' => $this->within($ballots(), 'created_at', $parameters)->count()],
            ['metric' => 'Ballots submitted', 'value' => $this->within($ballots()->submitted(), 'submitted_at', $parameters)->count()],
            ['metric' => 'Valid votes', 'value' => $this->within($ballots()->submitted()->valid(), 'submitted_at', $parameters)->count()],
            ['metric' => 'Cancelled votes', 'value' => $this->within($ballots()->submitted()->invalidated(), 'submitted_at', $parameters)->count()],
            [
                'metric' => 'Academy nominations submitted',
                'value' => $this->within(Nomination::query()->forEdition($edition)->submitted(), 'submitted_at', $parameters)->count(),
            ],
            [
                'metric' => 'Academy nominations in draft',
                'value' => Nomination::query()->forEdition($edition)->where('status', '=', NominationStatus::Draft->value)->count(),
            ],
        ];
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    private function within(Builder $query, string $column, ReportParameters $parameters): Builder
    {
        if ($parameters->from !== null) {
            $query->where($column, '>=', $parameters->from);
        }

        if ($parameters->to !== null) {
            $query->where($column, '<=', $parameters->to);
        }

        return $query;
    }
}
