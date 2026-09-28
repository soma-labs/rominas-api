<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Academy\Shortlist\Support\AcademyPointTally;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;

/**
 * Lists every nominee that received an academy nomination in a category, ranked by summed academy points
 * (points desc, then nominee id asc as a deterministic tie-break) — the full candidate pool the admin
 * reorders/trims down to the final shortlist in the manual review flow. Read-only.
 */
class GetCategoryShortlistCandidatesAction
{
    public function __construct(
        private readonly AcademyPointTally $tally,
    ) {}

    /**
     * @return list<array{nominee_type: string, nominee_id: int, name: string, slug: string, points: int, rank: int}>
     */
    public function execute(Edition $edition, Category $category): array
    {
        if ($category->edition_id !== $edition->id) {
            throw ValidationException::withMessages([
                'category' => __('The category does not belong to this edition.'),
            ]);
        }

        $points = $this->tally->perNominee($edition, $category);

        if ($points === []) {
            return [];
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $category->nominee_type->modelClass();

        /** @var \Illuminate\Support\Collection<int, object{name: string, slug: string}> $nominees */
        $nominees = $modelClass::query()
            ->whereIn('id', array_keys($points))
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $rows = [];

        foreach ($points as $id => $total) {
            $nominee = $nominees->get($id);

            $rows[] = [
                'nominee_type' => $category->nominee_type->value,
                'nominee_id' => $id,
                'name' => $nominee->name ?? '',
                'slug' => $nominee->slug ?? '',
                'points' => $total,
            ];
        }

        // Points desc, then nominee id asc (mirrors shortlist generation's ordering).
        usort(
            $rows,
            static fn(array $a, array $b): int => [$b['points'], $a['nominee_id']] <=> [$a['points'], $b['nominee_id']],
        );

        $rank = 1;

        return array_map(static function (array $row) use (&$rank): array {
            $row['rank'] = $rank++;

            return $row;
        }, $rows);
    }
}
