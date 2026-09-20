<?php

declare(strict_types=1);

namespace Rominas\Scoring\Support;

use Rominas\Scoring\DataTransferObjects\NomineeScore;

/**
 * The client's scoring algorithm (PHAZE 3–7, 2026-09-18). Rather than blending per-category shares, it maps
 * each class's ranking to a fixed rank→score ladder (the category weights are baked into the ladders) and
 * simply adds the two attributed scores:
 *
 * - PHAZE 3 — academy attributed score by the nominee's shortlist position: 1 → 200, 2 → 150, 3 → 100,
 *   4 → 75, 5 → 50. Position comes from the shortlist (`academy_position`), so a manual shortlist
 *   adjustment carries through.
 * - PHAZE 5/6 — the nominees are ranked by their summed public points and get a public attributed score by
 *   that rank: 1 → 150, 2 → 100, 3 → 75, 4 → 50, 5 → 25. Ties on public points break academy-first
 *   (higher academy position wins), then by nominee id; nominees with no public votes are still ranked.
 * - PHAZE 7 — final score = academy attributed + public attributed; highest wins. A tie is broken in favour
 *   of the higher academy attributed score, then by nominee id.
 *
 * The per-edition weights are ignored (baked into the ladders). Onto {@see NomineeScore}: `academyShare`
 * carries the academy attributed score, `publicShare` the public attributed score, and `finalScore` their
 * total — discrete points, NOT 0..1 shares. `academyPoints` / `publicPoints` stay the raw summed points.
 *
 * A category with fewer than 5 shortlisted nominees simply uses the first entries of each ladder.
 */
final class AttributedScoreCalculator implements ScoringAlgorithm
{
    /**
     * @param  list<int>  $academyLadder  attributed academy score by rank (index 0 = rank 1)
     * @param  list<int>  $publicLadder   attributed public score by rank (index 0 = rank 1)
     */
    public function __construct(
        private readonly array $academyLadder,
        private readonly array $publicLadder,
        private readonly int $precision,
    ) {}

    /**
     * @param  list<array{nominee_type: \Rominas\Catalog\Enums\NomineeType, nominee_id: int, academy_points: int, public_points: int, academy_position: int}>  $tallies
     * @return list<NomineeScore>
     */
    public function rank(array $tallies, int $academyWeight, int $publicWeight): array
    {
        $rows = $this->attribute($tallies);

        // Final ranking (PHAZE 7): total desc, then academy attributed desc, then nominee id asc.
        usort($rows, static fn(array $x, array $y): int => [
            $y['final'],
            $y['academy_attributed'],
            $x['tally']['nominee_id'],
        ] <=> [
            $x['final'],
            $x['academy_attributed'],
            $y['tally']['nominee_id'],
        ]);

        $scores = [];
        $position = 1;

        foreach ($rows as $row) {
            $tally = $row['tally'];

            $scores[] = new NomineeScore(
                nomineeType: $tally['nominee_type'],
                nomineeId: $tally['nominee_id'],
                academyPoints: $tally['academy_points'],
                publicPoints: $tally['public_points'],
                academyShare: round((float) $row['academy_attributed'], $this->precision),
                publicShare: round((float) $row['public_attributed'], $this->precision),
                finalScore: round((float) $row['final'], $this->precision),
                position: $position++,
            );
        }

        return $scores;
    }

    /**
     * The normalization step: attribute each nominee its academy score (by shortlist position) and public
     * score (by public-points rank), and their sum.
     *
     * @param  list<array{nominee_type: \Rominas\Catalog\Enums\NomineeType, nominee_id: int, academy_points: int, public_points: int, academy_position: int}>  $tallies
     * @return list<array{tally: array{nominee_type: \Rominas\Catalog\Enums\NomineeType, nominee_id: int, academy_points: int, public_points: int, academy_position: int}, academy_attributed: int, public_attributed: int, final: int}>
     */
    private function attribute(array $tallies): array
    {
        // PHAZE 5: rank by public points desc, academy-first tiebreak (position asc), then nominee id asc.
        $byPublic = $tallies;
        usort($byPublic, static fn(array $x, array $y): int => [
            $y['public_points'],
            $x['academy_position'],
            $x['nominee_id'],
        ] <=> [
            $x['public_points'],
            $y['academy_position'],
            $y['nominee_id'],
        ]);

        /** @var array<int, int> $publicRankByNominee  nominee id → public rank (1-based) */
        $publicRankByNominee = [];
        foreach ($byPublic as $index => $tally) {
            $publicRankByNominee[$tally['nominee_id']] = $index + 1;
        }

        $rows = [];

        foreach ($tallies as $tally) {
            $academyAttributed = $this->academyLadder[$tally['academy_position'] - 1] ?? 0;
            $publicAttributed = $this->publicLadder[$publicRankByNominee[$tally['nominee_id']] - 1] ?? 0;

            $rows[] = [
                'tally' => $tally,
                'academy_attributed' => $academyAttributed,
                'public_attributed' => $publicAttributed,
                'final' => $academyAttributed + $publicAttributed,
            ];
        }

        return $rows;
    }
}
