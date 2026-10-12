<?php

declare(strict_types=1);

namespace Rominas\Results\Support;

use Illuminate\Support\Collection;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Results\DataTransferObjects\EditionResults;
use Rominas\Scoring\DataTransferObjects\EditionScore;
use Rominas\SpecialAwards\DataTransferObjects\SpecialEntryResult;

/**
 * Turns an edition's {@see EditionResults} into a fully-resolved array for the API resource and CSV
 * export — enriching each category and nominee with its display name/slug. Lookups are batched (one query
 * per Catalog type, one for the categories) so rendering a whole edition stays free of N+1 queries.
 *
 * Every category carries its `kind` and both lists: a ranked category fills `nominees` and leaves
 * `entries` empty, a special one does the opposite. Categories come out in their own order, whatever
 * their kind.
 */
class EditionResultsPresenter
{
    /**
     * @param  bool  $withVotes  false leaves the member vote counts of the special categories out (the
     *                           public view shows who won, not by how much)
     * @return array{
     *     edition_id: int,
     *     edition: array{id: int, name: string, slug: string, status: string, status_label: string},
     *     source: string,
     *     algorithm: string,
     *     published_at: string|null,
     *     categories: list<array{
     *         category_id: int,
     *         category: array{id: int, name: string, slug: string}|null,
     *         kind: string,
     *         nominees: list<array{
     *             nominee_type: string,
     *             nominee_id: int,
     *             nominee: array{id: int, name: string, slug: string}|null,
     *             academy_points: int,
     *             public_points: int,
     *             academy_share: float,
     *             public_share: float,
     *             final_score: float,
     *             position: int
     *         }>,
     *         entries: list<array{title: string, description: string|null, votes: int|null, position: int}>
     *     }>
     * }
     */
    public function present(EditionResults $results, bool $withVotes = true): array
    {
        $score = $results->score;
        $categories = $this->resolveCategories($results);
        $nominees = $this->resolveNominees($score);

        $mappedCategories = [];

        foreach ($score->categories as $category) {
            $mappedNominees = [];

            foreach ($category->nominees as $nominee) {
                $nomineeModel = $nominees[$nominee->nomineeType->value][$nominee->nomineeId] ?? null;

                $mappedNominees[] = [
                    'nominee_type' => $nominee->nomineeType->value,
                    'nominee_id' => $nominee->nomineeId,
                    'nominee' => $nomineeModel === null ? null : [
                        'id' => $nominee->nomineeId,
                        'name' => $nomineeModel->name,
                        'slug' => $nomineeModel->slug,
                    ],
                    'academy_points' => $nominee->academyPoints,
                    'public_points' => $nominee->publicPoints,
                    'academy_share' => $nominee->academyShare,
                    'public_share' => $nominee->publicShare,
                    'final_score' => $nominee->finalScore,
                    'position' => $nominee->position,
                ];
            }

            $mappedCategories[] = [
                'category_id' => $category->categoryId,
                'category' => $this->categoryLabel($categories->get($category->categoryId)),
                'kind' => CategoryKind::Ranked->value,
                'nominees' => $mappedNominees,
                'entries' => [],
            ];
        }

        foreach ($results->specialCategories as $category) {
            $categoryModel = $categories->get($category->categoryId);

            $mappedCategories[] = [
                'category_id' => $category->categoryId,
                'category' => $this->categoryLabel($categoryModel),
                'kind' => ($categoryModel->kind ?? CategoryKind::ProposeOrVote)->value,
                'nominees' => [],
                'entries' => array_map(
                    static fn(SpecialEntryResult $entry): array => [
                        'title' => $entry->title,
                        'description' => $entry->description,
                        'votes' => $withVotes ? $entry->votes : null,
                        'position' => $entry->position,
                    ],
                    $category->entries,
                ),
            ];
        }

        // One list in category order, whatever the kind: position first, then id as a stable tie-break.
        usort(
            $mappedCategories,
            static fn(array $a, array $b): int => [$categories->get($a['category_id'])->position ?? 0, $a['category_id']]
                <=> [$categories->get($b['category_id'])->position ?? 0, $b['category_id']],
        );

        return [
            'edition_id' => $score->editionId,
            'edition' => [
                'id' => $results->edition->id,
                'name' => $results->edition->name,
                'slug' => $results->edition->slug,
                'status' => $results->edition->status->value,
                'status_label' => $results->edition->status->label(),
            ],
            'source' => $results->source->value,
            'algorithm' => $score->algorithm->value,
            'published_at' => $results->publishedAt?->toIso8601String(),
            'categories' => $mappedCategories,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string}|null
     */
    private function categoryLabel(?Category $category): ?array
    {
        return $category === null ? null : [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }

    /**
     * @return Collection<int, Category>
     */
    private function resolveCategories(EditionResults $results): Collection
    {
        $ids = [
            ...array_map(static fn($category): int => $category->categoryId, $results->score->categories),
            ...array_map(static fn($category): int => $category->categoryId, $results->specialCategories),
        ];

        if ($ids === []) {
            return new Collection();
        }

        return Category::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'slug', 'kind', 'position'])
            ->keyBy('id');
    }

    /**
     * Nominee display data grouped by NomineeType slug then keyed by id, one query per type.
     *
     * @return array<string, Collection<int, object{name: string, slug: string}>>
     */
    private function resolveNominees(EditionScore $score): array
    {
        /** @var array<string, list<int>> $idsByType */
        $idsByType = [];

        foreach ($score->categories as $category) {
            foreach ($category->nominees as $nominee) {
                $idsByType[$nominee->nomineeType->value][] = $nominee->nomineeId;
            }
        }

        $resolved = [];

        foreach ($score->categories as $category) {
            foreach ($category->nominees as $nominee) {
                $type = $nominee->nomineeType;

                if (isset($resolved[$type->value])) {
                    continue;
                }

                /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
                $modelClass = $type->modelClass();

                /** @var Collection<int, object{name: string, slug: string}> $models */
                $models = $modelClass::query()
                    ->whereIn('id', $idsByType[$type->value])
                    ->get(['id', 'name', 'slug'])
                    ->keyBy('id');

                $resolved[$type->value] = $models;
            }
        }

        return $resolved;
    }
}
