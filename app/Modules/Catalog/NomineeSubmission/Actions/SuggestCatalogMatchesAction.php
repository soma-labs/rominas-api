<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Actions;

use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Catalog\NomineeSubmission\Support\NameSimilarity;

/**
 * Ranks existing Catalog entries of the submission's type by how closely they match the typed name, so an
 * admin can link with one click instead of scanning the whole catalog. An exact normalized match scores
 * 100; the rest are scored by an order-independent, fuzzy token-set overlap (see {@see NameSimilarity}).
 * Catalogs are small, so candidates are compared in PHP — a DB `LIKE`/`SOUNDEX` prefilter is a future
 * optimization if a type's table grows large.
 */
class SuggestCatalogMatchesAction
{
    private const int DEFAULT_LIMIT = 5;

    /**
     * @return list<array{id: int, name: string, slug: string, score: float}>
     */
    public function execute(NomineeSubmission $submission, int $limit = self::DEFAULT_LIMIT): array
    {
        $modelClass = $submission->nominee_type->modelClass();

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
                'score' => NameSimilarity::score($submission->raw_name, (string) $candidate->name),
            ]);

        return $scored
            ->filter(fn(array $row): bool => $row['score'] > 0.0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }
}
