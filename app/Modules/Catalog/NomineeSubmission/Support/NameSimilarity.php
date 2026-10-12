<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Support;

/**
 * How closely two typed names match, independent of case, punctuation and word order. Shared by the
 * Catalog match suggestions and the special-award entry duplicates check, so an admin sees the same idea
 * of "similar" everywhere.
 */
class NameSimilarity
{
    /**
     * Per-token similarity (0–1) below which two words are treated as unrelated and contribute nothing.
     * Keeps typos matching ("matache" ≈ "matace") while stopping incidental shared letters between
     * unrelated words (e.g. "aurelian" vs "matache") from inflating the total.
     */
    private const float TOKEN_MATCH_THRESHOLD = 0.7;

    /**
     * Similarity in [0, 100]. An exact normalized match is 100; otherwise a soft Dice coefficient over the
     * two token sets, where each token earns its best per-token match in the other set (below
     * {@see TOKEN_MATCH_THRESHOLD} it earns nothing, so unrelated names score 0).
     */
    public static function score(string $name, string $candidate): float
    {
        if (NomineeNameNormalizer::normalize($name) === NomineeNameNormalizer::normalize($candidate)) {
            return 100.0;
        }

        $left = array_values(array_unique(NomineeNameNormalizer::tokens($name)));
        $right = array_values(array_unique(NomineeNameNormalizer::tokens($candidate)));
        $total = count($left) + count($right);

        if ($total === 0) {
            return 0.0;
        }

        $overlap = self::softOverlap($left, $right) + self::softOverlap($right, $left);

        return round($overlap / $total * 100, 1);
    }

    /**
     * Sum, over each token in $from, of its best per-token similarity to any token in $to — counting only
     * matches at or above the threshold, so coincidental letter overlap between unrelated words is ignored.
     *
     * @param  list<string>  $from
     * @param  list<string>  $to
     */
    private static function softOverlap(array $from, array $to): float
    {
        $sum = 0.0;

        foreach ($from as $fromToken) {
            $best = 0.0;

            foreach ($to as $toToken) {
                $best = max($best, self::tokenSimilarity($fromToken, $toToken));
            }

            $sum += $best >= self::TOKEN_MATCH_THRESHOLD ? $best : 0.0;
        }

        return $sum;
    }

    /**
     * Per-token similarity in [0, 1]: 1 for an exact token, otherwise PHP's percent-similarity scaled down.
     */
    private static function tokenSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
