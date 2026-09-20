<?php

declare(strict_types=1);

namespace Rominas\Voting\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Voting\Model\Ballot;

/**
 * The voting ballot payload: the edition, the ballot status, and each category's shortlisted nominees the
 * voter ranks (grouped by category). Nominees are presented in ALPHABETICAL order by name (PHAZE 4) so the
 * public ballot never leaks the secret academy shortlist order; unresolved nominees sort last. Nominees
 * are flattened to `{id, name, slug}`, matching ShortlistEntryResource.
 */
class BallotResource extends JsonResource
{
    /**
     * @param  Collection<int, ShortlistEntry>  $shortlist
     */
    public function __construct(
        Ballot $ballot,
        private readonly Collection $shortlist,
    ) {
        parent::__construct($ballot);
    }

    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var Ballot $ballot */
        $ballot = $this->resource;

        $categories = [];

        foreach ($this->shortlist->groupBy('category_id') as $entries) {
            /** @var ShortlistEntry $first */
            $first = $entries->first();
            $category = $first->category;

            // Alphabetical by nominee name (PHAZE 4); unresolved nominees sort last. Never expose the
            // academy shortlist position — that ranking stays secret until results.
            $ordered = $entries
                ->sortBy(fn(ShortlistEntry $entry): string => $entry->nominee->name ?? "\u{FFFF}")
                ->values();

            $nominees = [];
            foreach ($ordered as $entry) {
                /** @var object{id: int, name: string, slug: string}|null $nominee */
                $nominee = $entry->nominee;

                $nominees[] = [
                    'nominee_type' => $entry->nominee_type->value,
                    'nominee_id' => $entry->nominee_id,
                    'nominee' => $nominee === null ? null : [
                        'id' => $nominee->id,
                        'name' => $nominee->name,
                        'slug' => $nominee->slug,
                    ],
                ];
            }

            $categories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'nominee_type' => $category->nominee_type->value,
                'nominees' => $nominees,
            ];
        }

        return [
            'edition' => [
                'id' => $ballot->edition->id,
                'name' => $ballot->edition->name,
            ],
            'status' => $ballot->status->value,
            'categories' => $categories,
        ];
    }
}
