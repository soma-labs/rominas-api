<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Reporting\Support\EntityLabeler;
use Rominas\Scoring\PublicRankPoints;
use Rominas\Voting\Enums\BallotStatus;
use Rominas\Voting\Model\BallotRanking;

/**
 * Each entity's public "vote weight" per category: the rank-weighted sum of the public rankings on
 * submitted, non-cancelled ballots, using the public {@see PublicRankPoints} curve (rank 1 = 10 pts,
 * 2 = 8, 3 = 6). A plain count would be less telling — the public ranks its 3 picks — so points are what
 * differentiate entities. Fraud-cancelled ballots are excluded; optionally narrowed to a `submitted_at`
 * window. Points are accumulated in PHP over the per-(entity, rank) counts so the curve stays
 * single-sourced in `PublicRankPoints`.
 */
final class PublicVotesPerEntityReport implements ReportInterface
{
    public function __construct(private readonly EntityLabeler $labeler) {}

    public function key(): string
    {
        return 'public-votes-per-entity';
    }

    public function title(): string
    {
        return 'Public Votes per Entity';
    }

    public function columns(): array
    {
        return ['category', 'entity_type', 'entity', 'points'];
    }

    public function rows(ReportParameters $parameters): iterable
    {
        $query = BallotRanking::query()
            ->join('ballots', 'ballots.id', '=', 'ballot_rankings.ballot_id')
            ->where('ballots.edition_id', '=', $parameters->edition->id)
            ->where('ballots.status', '=', BallotStatus::Submitted->value)
            ->whereNull('ballots.invalidation_batch_id');

        if ($parameters->from !== null) {
            $query->where('ballots.submitted_at', '>=', $parameters->from);
        }

        if ($parameters->to !== null) {
            $query->where('ballots.submitted_at', '<=', $parameters->to);
        }

        $grouped = $query
            ->groupBy('ballot_rankings.category_id', 'ballot_rankings.nominee_type', 'ballot_rankings.nominee_id', 'ballot_rankings.rank')
            ->selectRaw(
                'ballot_rankings.category_id as category_id, ballot_rankings.nominee_type as nominee_type, '
                . 'ballot_rankings.nominee_id as nominee_id, ballot_rankings.rank as vote_rank, COUNT(*) as n',
            )
            ->toBase()
            ->get();

        /** @var array<string, array{category_id: int, nominee_type: string, nominee_id: int, value: int}> $points */
        $points = [];

        foreach ($grouped as $row) {
            $categoryId = (int) $row->category_id;
            $nomineeType = (string) $row->nominee_type;
            $nomineeId = (int) $row->nominee_id;
            $key = $categoryId . ':' . $nomineeType . ':' . $nomineeId;

            $points[$key] ??= [
                'category_id' => $categoryId,
                'nominee_type' => $nomineeType,
                'nominee_id' => $nomineeId,
                'value' => 0,
            ];

            $points[$key]['value'] += PublicRankPoints::forRank((int) $row->vote_rank) * (int) $row->n;
        }

        $entries = array_values($points);

        usort($entries, static fn(array $a, array $b): int => $a['category_id'] <=> $b['category_id'] ?: $b['value'] <=> $a['value']);

        return $this->labeler->labelEntityRows($entries, 'points');
    }
}
