<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Actions;

use Illuminate\Database\Eloquent\Collection;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;

/**
 * Approved entries of the category whose title contains what the member has typed so far, offered while
 * they write a proposal so that an entry that already exists becomes a vote instead of a duplicate.
 * Titles that start with the typed text come first, then the rest, each alphabetically. Pending entries
 * are never offered.
 */
class SuggestSpecialEntriesAction
{
    private const int LIMIT = 8;

    /**
     * @return Collection<int, SpecialEntry>
     */
    public function execute(Category $category, string $search): Collection
    {
        // The typed text is matched literally: `%` and `_` are not wildcards here.
        $term = addcslashes($search, '\\%_');

        return SpecialEntry::query()
            ->forCategory($category)
            ->approved()
            ->where('title', 'LIKE', "%{$term}%")
            ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ["{$term}%"])
            ->orderBy('title', 'asc')
            ->limit(self::LIMIT)
            ->get();
    }
}
