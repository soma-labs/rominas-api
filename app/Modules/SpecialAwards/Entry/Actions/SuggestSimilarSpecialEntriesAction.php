<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Rominas\Catalog\NomineeSubmission\Support\NameSimilarity;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;

/**
 * Ranks the approved entries of the same category by how closely their title matches this entry's, so an
 * admin reviewing a proposal sees the likely duplicates and can merge instead of approving a second
 * spelling. Same matching as the Catalog reconciliation suggestions ({@see NameSimilarity}).
 */
class SuggestSimilarSpecialEntriesAction
{
    private const int DEFAULT_LIMIT = 5;

    /**
     * @return list<array{id: int, title: string, score: float}>
     */
    public function execute(SpecialEntry $entry, int $limit = self::DEFAULT_LIMIT): array
    {
        /** @var \Illuminate\Support\Collection<int, array{id: int, title: string, score: float}> $scored */
        $scored = SpecialEntry::query()
            ->forCategory($entry->category)
            ->approved()
            ->whereKeyNot($entry->id)
            ->toBase()
            ->get(['id', 'title'])
            ->map(fn(object $candidate): array => [
                'id' => (int) $candidate->id,
                'title' => (string) $candidate->title,
                'score' => NameSimilarity::score($entry->title, (string) $candidate->title),
            ]);

        return $scored
            ->filter(fn(array $row): bool => $row['score'] > 0.0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }
}
