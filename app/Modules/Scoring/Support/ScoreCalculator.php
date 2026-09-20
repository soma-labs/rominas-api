<?php

declare(strict_types=1);

namespace Rominas\Scoring\Support;

use Rominas\Scoring\DataTransferObjects\NomineeScore;

/**
 * The scoring engine, pure and DB-free. Given each nominee's summed academy and public points within a
 * category, it normalizes each class to a share of that class's own total (so the two scales — dozens of
 * academy members vs. potentially thousands of voters — become comparable), weights them, and ranks.
 *
 * Normalization is per-category share: `share = points / that class's category total`. The final score is
 *
 *     finalScore = (academyWeight · academyShare + publicWeight · publicShare) / (academyWeight + publicWeight)
 *
 * Ranking is NOT done on that float. Multiplying through by the (per-category constant) class totals gives
 * an exact integer key that preserves the order without floating-point drift:
 *
 *     rankKey = academyWeight · academyPoints · publicTotal + publicWeight · publicPoints · academyTotal
 *
 * so the float is only ever rounded for display. Ties are broken deterministically: academy points, then
 * public points, then nominee id — i.e. a dead heat resolves in favour of the higher-weighted expert class.
 *
 * Edge cases (a category is uniformly one branch, since every nominee shares the same totals):
 * - no public votes  (publicTotal = 0)  → renormalize to academy 100%: finalScore = academyShare.
 * - no academy points (academyTotal = 0) → public determines the result: finalScore = publicShare.
 *
 * The class weights are per-edition (`editions.academy_vote_weight` / `public_vote_weight`), so they are
 * passed to {@see rank()} rather than fixed on the instance; only display precision is instance state.
 *
 * This is the `share` scoring algorithm; {@see NomineeScore}'s `academyShare` / `publicShare` are the 0..1
 * class shares and `finalScore` is the 0..1 weighted blend. The `academy_position` tally key is unused here.
 */
final class ScoreCalculator implements ScoringAlgorithm
{
    public function __construct(
        private readonly int $precision,
    ) {}

    /**
     * Rank a category's nominees best-first, assigning positions 1..n.
     *
     * @param  list<array{nominee_type: \Rominas\Catalog\Enums\NomineeType, nominee_id: int, academy_points: int, public_points: int, academy_position: int}>  $tallies
     * @return list<NomineeScore>
     */
    public function rank(array $tallies, int $academyWeight, int $publicWeight): array
    {
        $academyTotal = 0;
        $publicTotal = 0;

        foreach ($tallies as $tally) {
            $academyTotal += $tally['academy_points'];
            $publicTotal += $tally['public_points'];
        }

        $weightTotal = $academyWeight + $publicWeight;

        $rows = [];

        foreach ($tallies as $tally) {
            $academyPoints = $tally['academy_points'];
            $publicPoints = $tally['public_points'];

            $academyShare = $this->normalize($academyPoints, $academyTotal);
            $publicShare = $this->normalize($publicPoints, $publicTotal);

            if ($publicTotal === 0) {
                // No public votes → the public weight is void; academy alone decides (order by academyShare).
                $rankKey = $academyWeight * $academyPoints;
                $finalScore = $academyShare;
            } elseif ($academyTotal === 0) {
                // Defensive: no academy points → public determines the result.
                $rankKey = $publicWeight * $publicPoints;
                $finalScore = $publicShare;
            } else {
                $rankKey = $academyWeight * $academyPoints * $publicTotal
                    + $publicWeight * $publicPoints * $academyTotal;
                $finalScore = $rankKey / ($weightTotal * $academyTotal * $publicTotal);
            }

            $rows[] = [
                'tally' => $tally,
                'rank_key' => $rankKey,
                'academy_share' => $academyShare,
                'public_share' => $publicShare,
                'final_score' => $finalScore,
            ];
        }

        // rank_key desc, then academy points desc, then public points desc, then nominee id asc.
        usort($rows, static fn(array $x, array $y): int => [
            $y['rank_key'],
            $y['tally']['academy_points'],
            $y['tally']['public_points'],
            $x['tally']['nominee_id'],
        ] <=> [
            $x['rank_key'],
            $x['tally']['academy_points'],
            $x['tally']['public_points'],
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
                academyShare: round($row['academy_share'], $this->precision),
                publicShare: round($row['public_share'], $this->precision),
                finalScore: round($row['final_score'], $this->precision),
                position: $position++,
            );
        }

        return $scores;
    }

    /**
     * Normalize a nominee's points to its share (0..1) of the class's category total. A zero total (the
     * class had no votes) yields 0 — the caller's edge-case handling renormalizes onto the other class.
     */
    private function normalize(int $points, int $classTotal): float
    {
        return $classTotal > 0 ? $points / $classTotal : 0.0;
    }
}
