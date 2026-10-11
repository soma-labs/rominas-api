<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Actions;

use Rominas\Academy\Nomination\DataTransferObjects\NomineeSuggestionData;
use Rominas\Categories\Model\Category;

/**
 * Catalog entries of the category's nominee type whose name contains what the member has typed so far,
 * offered as suggestions on the ballot. Names that start with the typed text come first, then the rest,
 * each alphabetically. Only the Catalog is searched — never the names other members typed.
 */
class SuggestNomineesForCategoryAction
{
    private const int LIMIT = 8;

    /**
     * @return list<NomineeSuggestionData>
     */
    public function execute(Category $category, string $search): array
    {
        $modelClass = $category->nominee_type->modelClass();

        // The typed text is matched literally: `%` and `_` are not wildcards here.
        $term = addcslashes($search, '\\%_');

        // toBase() yields stdClass rows — the concrete Catalog model is only known at runtime.
        return $modelClass::query()
            ->where('name', 'LIKE', "%{$term}%")
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$term}%"])
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->toBase()
            ->get(['id', 'name'])
            ->map(fn(object $entry): NomineeSuggestionData => new NomineeSuggestionData(
                id: (int) $entry->id,
                name: (string) $entry->name,
            ))
            ->values()
            ->all();
    }
}
