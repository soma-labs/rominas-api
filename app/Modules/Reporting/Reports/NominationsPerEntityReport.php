<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Reporting\Support\EntityLabeler;

/**
 * How many times each entity was nominated by the academy, per category — a count of the ranked picks on
 * submitted academy nominations (the academy's only input; the client's "nominations" and "academy votes"
 * are one metric). Draft nominations are excluded. Optionally narrowed to a `submitted_at` window.
 */
final class NominationsPerEntityReport implements ReportInterface
{
    public function __construct(private readonly EntityLabeler $labeler) {}

    public function key(): string
    {
        return 'nominations-per-entity';
    }

    public function title(): string
    {
        return 'Nominations per Entity';
    }

    public function columns(): array
    {
        return ['category', 'entity_type', 'entity', 'nominations'];
    }

    public function rows(ReportParameters $parameters): iterable
    {
        $query = NominationRanking::query()
            ->join('nominations', 'nominations.id', '=', 'nomination_rankings.nomination_id')
            ->where('nominations.edition_id', '=', $parameters->edition->id)
            ->where('nominations.status', '=', NominationStatus::Submitted->value)
            ->resolved();

        if ($parameters->from !== null) {
            $query->where('nominations.submitted_at', '>=', $parameters->from);
        }

        if ($parameters->to !== null) {
            $query->where('nominations.submitted_at', '<=', $parameters->to);
        }

        $entries = $query
            ->groupBy('nomination_rankings.category_id', 'nomination_rankings.nominee_type', 'nomination_rankings.nominee_id')
            ->orderBy('nomination_rankings.category_id')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw(
                'nomination_rankings.category_id as category_id, nomination_rankings.nominee_type as nominee_type, '
                . 'nomination_rankings.nominee_id as nominee_id, COUNT(*) as nominations',
            )
            ->toBase()
            ->get()
            ->map(fn(object $row): array => [
                'category_id' => (int) $row->category_id,
                'nominee_type' => (string) $row->nominee_type,
                'nominee_id' => (int) $row->nominee_id,
                'value' => (int) $row->nominations,
            ])
            ->all();

        return $this->labeler->labelEntityRows($entries, 'nominations');
    }
}
