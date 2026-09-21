<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Catalog\NomineeSubmission\Support\NomineeNameNormalizer;

/**
 * Ranks existing Catalog entries of the submission's type by how closely they match the typed name, so an
 * admin can link with one click instead of scanning the whole catalog. An exact normalized match scores
 * 100; the rest are scored by an order-independent, fuzzy token-set overlap (see {@see score()}). Catalogs
 * are small, so candidates are compared in PHP — a DB `LIKE`/`SOUNDEX` prefilter is a future optimization
 * if a type's table grows large.
 */
class SuggestCatalogMatchesAction
{
    private const int DEFAULT_LIMIT = 5;

    /**
     * Per-token similarity (0–1) below which two words are treated as unrelated and contribute nothing.
     * Keeps typos matching ("matache" ≈ "matace") while stopping incidental shared letters between
     * unrelated words (e.g. "aurelian" vs "matache") from inflating the total.
     */
    private const float TOKEN_MATCH_THRESHOLD = 0.7;

    /**
     * @return list<array{id: int, name: string, slug: string, score: float}>
     */
    public function execute(NomineeSubmission $submission, int $limit = self::DEFAULT_LIMIT): array
    {
        $modelClass = $submission->nominee_type->modelClass();
        $target = $submission->normalized_name;
        $targetTokens = NomineeNameNormalizer::tokens($submission->raw_name);

        // toBase() yields stdClass rows (id/name/slug) — the concrete Catalog model behind $modelClass is
        // only known at runtime, so this keeps the comparison off the generic Eloquent Model type.
        /** @var \Illuminate\Support\Collection<int, array{id: int, name: string, slug: string, score: float}> $scored */
        $scored = $modelClass::query()
            ->toBase()
            ->get(['id', 'name', 'slug'])
            ->map(fn(object $candidate): array => [
                'id' => (int) $candidate->id,
                'name' => (string) $candidate->name,
                'slug' => (string) $candidate->slug,
                'score' => $this->score(
                    $target,
                    $targetTokens,
                    NomineeNameNormalizer::normalize((string) $candidate->name),
                    NomineeNameNormalizer::tokens((string) $candidate->name),
                ),
            ]);

        return $scored
            ->filter(fn(array $row): bool => $row['score'] > 0.0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Similarity in [0, 100], independent of word order. An exact normalized match is 100; otherwise a
     * soft Dice coefficient over the two token sets, where each token earns its best per-token match in
     * the other set (below {@see TOKEN_MATCH_THRESHOLD} it earns nothing, so unrelated names score 0).
     *
     * @param  list<string>  $targetTokens
     * @param  list<string>  $candidateTokens
     */
    private function score(string $target, array $targetTokens, string $candidate, array $candidateTokens): float
    {
        if ($target === $candidate) {
            return 100.0;
        }

        $left = array_values(array_unique($targetTokens));
        $right = array_values(array_unique($candidateTokens));
        $total = count($left) + count($right);

        if ($total === 0) {
            return 0.0;
        }

        $overlap = $this->softOverlap($left, $right) + $this->softOverlap($right, $left);

        return round($overlap / $total * 100, 1);
    }

    /**
     * Sum, over each token in $from, of its best per-token similarity to any token in $to — counting only
     * matches at or above the threshold, so coincidental letter overlap between unrelated words is ignored.
     *
     * @param  list<string>  $from
     * @param  list<string>  $to
     */
    private function softOverlap(array $from, array $to): float
    {
        $sum = 0.0;

        foreach ($from as $fromToken) {
            $best = 0.0;

            foreach ($to as $toToken) {
                $best = max($best, $this->tokenSimilarity($fromToken, $toToken));
            }

            $sum += $best >= self::TOKEN_MATCH_THRESHOLD ? $best : 0.0;
        }

        return $sum;
    }

    /**
     * Per-token similarity in [0, 1]: 1 for an exact token, otherwise PHP's percent-similarity scaled down.
     */
    private function tokenSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
