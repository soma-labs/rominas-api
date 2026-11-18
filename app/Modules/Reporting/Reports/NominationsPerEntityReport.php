<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Academy\Nomination\QueryBuilders\NominationRankingQueryBuilder;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Reporting\Support\EntityLabeler;
use Rominas\Scoring\RankPoints;

/**
 * How many times each entity was nominated by the academy, per category — a count of the ranked picks on
 * submitted academy nominations (the academy's only input; the client's "nominations" and "academy votes"
 * are one metric) — and the academy `points` it earned on the {@see RankPoints} curve (10·8·6·4·2), the
 * same points the shortlist and Scoring use. Draft nominations are excluded. Optionally narrowed to a
 * `submitted_at` window and to one category.
 *
 * Categories follow their `position`; inside a category the entities are in shortlist order: points
 * desc, then nominee id asc ({@see \Rominas\Academy\Shortlist\Actions\GenerateCategoryShortlistAction}).
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
        return ['category', 'entity_type', 'entity', 'nominations', 'points'];
    }

    public function categoryFilterKinds(): array
    {
        return [CategoryKind::Ranked];
    }

    public function rows(ReportParameters $parameters): iterable
    {
        $query = NominationRanking::query()
            ->join('nominations', 'nominations.id', '=', 'nomination_rankings.nomination_id')
            ->join('categories', 'categories.id', '=', 'nomination_rankings.category_id')
            ->where('nominations.edition_id', '=', $parameters->edition->id)
            ->where('nominations.status', '=', NominationStatus::Submitted->value)
            ->resolved();

        if ($parameters->category !== null) {
            $query->where('nomination_rankings.category_id', '=', $parameters->category->id);
        }

        if ($parameters->from !== null) {
            $query->where('nominations.submitted_at', '>=', $parameters->from);
        }

        if ($parameters->to !== null) {
            $query->where('nominations.submitted_at', '<=', $parameters->to);
        }

        $aggregates = $query
            ->groupBy(
                'categories.position',
                'nomination_rankings.category_id',
                'nomination_rankings.nominee_type',
                'nomination_rankings.nominee_id',
            )
            ->selectRaw(
                'nomination_rankings.category_id as category_id, nomination_rankings.nominee_type as nominee_type, '
                . 'nomination_rankings.nominee_id as nominee_id, COUNT(*) as nominations',
            )
            ->selectRaw($this->academyPointsSql($query), $this->academyPointsBindings())
            ->orderBy('categories.position')
            ->orderBy('nomination_rankings.category_id')
            ->orderByRaw('points DESC')
            ->orderBy('nomination_rankings.nominee_id')
            ->toBase()
            ->get()
            ->values();

        $rows = $this->labeler->labelEntityRows(
            $aggregates->map(fn(object $row): array => [
                'category_id' => (int) $row->category_id,
                'nominee_type' => (string) $row->nominee_type,
                'nominee_id' => (int) $row->nominee_id,
                'value' => (int) $row->nominations,
            ])->all(),
            'nominations',
        );

        foreach ($aggregates as $index => $aggregate) {
            $rows[$index]['points'] = (int) $aggregate->points;
        }

        return $rows;
    }

    /**
     * The {@see RankPoints} curve as a `CASE` on the rank, summed per entity (values bound, see
     * {@see academyPointsBindings()}).
     */
    private function academyPointsSql(NominationRankingQueryBuilder $query): string
    {
        $rankColumn = $query->getQuery()->getGrammar()->wrap('nomination_rankings.rank');
        $cases = str_repeat('WHEN ? THEN ? ', RankPoints::RANKS);

        return 'SUM(CASE ' . $rankColumn . ' ' . $cases . 'ELSE 0 END) as points';
    }

    /**
     * @return list<int>  rank, points, rank, points, …
     */
    private function academyPointsBindings(): array
    {
        $bindings = [];

        foreach (RankPoints::pointsByRank() as $rank => $points) {
            $bindings[] = $rank;
            $bindings[] = $points;
        }

        return $bindings;
    }
}
