<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Nomination\QueryBuilders\NominationRankingQueryBuilder;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Reporting\Support\EntityLabeler;
use Rominas\Scoring\RankPoints;

/**
 * How many times each entity was nominated by the academy, per category — a count of the ranked picks on
 * submitted academy nominations (the academy's only input; the client's "nominations" and "academy votes"
 * are one metric). Draft nominations are excluded. Optionally narrowed to a `submitted_at` window.
 *
 * For a viewer holding the `results` permission it adds `points`: the academy rank points each entity
 * earned on the {@see RankPoints} curve (10·8·6·4·2), the same academy points Scoring uses. Points are a
 * results input, so the column stays custodian-only before publication; admins see the count alone.
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

    public function columns(bool $includeResultsData = false): array
    {
        $columns = ['category', 'entity_type', 'entity', 'nominations'];

        if ($includeResultsData) {
            $columns[] = 'points';
        }

        return $columns;
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

        $query
            ->groupBy('nomination_rankings.category_id', 'nomination_rankings.nominee_type', 'nomination_rankings.nominee_id')
            ->orderBy('nomination_rankings.category_id')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw(
                'nomination_rankings.category_id as category_id, nomination_rankings.nominee_type as nominee_type, '
                . 'nomination_rankings.nominee_id as nominee_id, COUNT(*) as nominations',
            );

        if ($parameters->includeResultsData) {
            $this->selectAcademyPoints($query);
        }

        $aggregates = $query->toBase()->get();

        $rows = $this->labeler->labelEntityRows(
            $aggregates->map(fn(object $row): array => [
                'category_id' => (int) $row->category_id,
                'nominee_type' => (string) $row->nominee_type,
                'nominee_id' => (int) $row->nominee_id,
                'value' => (int) $row->nominations,
            ])->all(),
            'nominations',
        );

        if (! $parameters->includeResultsData) {
            return $rows;
        }

        foreach ($aggregates->values() as $index => $aggregate) {
            $rows[$index]['points'] = (int) $aggregate->points;
        }

        return $rows;
    }

    /**
     * Sum each entity's academy points in SQL — the {@see RankPoints} curve as a `CASE` on the rank (bound
     * values) — and break count ties by points.
     */
    private function selectAcademyPoints(NominationRankingQueryBuilder $query): void
    {
        $rankColumn = $query->getQuery()->getGrammar()->wrap('nomination_rankings.rank');
        $cases = [];
        $bindings = [];

        foreach (RankPoints::pointsByRank() as $rank => $points) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $rank;
            $bindings[] = $points;
        }

        $query
            ->selectRaw('SUM(CASE ' . $rankColumn . ' ' . implode(' ', $cases) . ' ELSE 0 END) as points', $bindings)
            ->orderByRaw('points DESC');
    }
}
