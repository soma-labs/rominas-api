<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;

/**
 * Bulk convenience: (re)generates the shortlist for every ranked category in the edition, delegating each to
 * {@see GenerateCategoryShortlistAction}. The whole run is one transaction — either all categories are
 * regenerated or none. Same `nominations_closed` guard, checked once up front.
 */
class GenerateEditionShortlistsAction
{
    public function __construct(
        private readonly GenerateCategoryShortlistAction $generateCategory,
    ) {}

    /**
     * @return Collection<int, ShortlistEntry>
     */
    public function execute(Edition $edition): Collection
    {
        $this->generateCategory->assertGeneratable($edition);

        $categories = Category::query()->filterByEditionId($edition->id)->ranked()->get();

        return DB::transaction(function () use ($edition, $categories): Collection {
            $entries = new Collection();

            foreach ($categories as $category) {
                $entries = $entries->merge($this->generateCategory->execute($edition, $category));
            }

            return $entries;
        });
    }
}
