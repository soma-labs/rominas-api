<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Actions;

use Illuminate\Database\Eloquent\Collection;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\SpecialAwards\DataTransferObjects\SpecialCategoryResult;
use Rominas\SpecialAwards\DataTransferObjects\SpecialEntryResult;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;

/**
 * Computes the result of every special category of an edition, in category order.
 *
 * - Propose-or-vote: the approved entries ordered by member votes, most first. Entries with the same
 *   number of votes share a position (two at 5 votes are both 1st, the next is 3rd) — a tie is shown as
 *   a tie, never broken silently.
 * - Appointed: the one entry an admin entered, with no votes.
 *
 * Pending, rejected and merged entries never count: a merged entry's votes already sit on its target.
 */
class ComputeSpecialResultsAction
{
    /**
     * @return list<SpecialCategoryResult>
     */
    public function execute(Edition $edition): array
    {
        $categories = Category::query()
            ->filterByEditionId($edition->id)
            ->special()
            ->get()
            ->sortBy('position')
            ->values();

        $entries = SpecialEntry::query()
            ->approved()
            ->whereIn('category_id', $categories->modelKeys())
            ->withCount('votes')
            ->get()
            ->groupBy('category_id');

        $results = [];

        foreach ($categories as $category) {
            /** @var Collection<int, SpecialEntry> $categoryEntries */
            $categoryEntries = $entries->get($category->id) ?? new Collection();

            $results[] = new SpecialCategoryResult(
                $category->id,
                $category->kind === CategoryKind::Appointed
                    ? $this->appointed($categoryEntries)
                    : $this->byVotes($categoryEntries),
            );
        }

        return $results;
    }

    /**
     * @param  Collection<int, SpecialEntry>  $entries
     * @return list<SpecialEntryResult>
     */
    private function appointed(Collection $entries): array
    {
        return $entries
            ->take(1)
            ->map(static fn(SpecialEntry $entry): SpecialEntryResult => new SpecialEntryResult(
                title: $entry->title,
                votes: null,
                position: 1,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SpecialEntry>  $entries
     * @return list<SpecialEntryResult>
     */
    private function byVotes(Collection $entries): array
    {
        // Votes desc, then title as a stable order inside a tie.
        $ordered = $entries
            ->sort(static fn(SpecialEntry $a, SpecialEntry $b): int => [(int) $b->votes_count, $a->title] <=> [(int) $a->votes_count, $b->title])
            ->values();

        $results = [];
        $position = 0;
        $previousVotes = null;

        foreach ($ordered as $index => $entry) {
            $votes = (int) $entry->votes_count;

            if ($votes !== $previousVotes) {
                $position = $index + 1;
                $previousVotes = $votes;
            }

            $results[] = new SpecialEntryResult(
                title: $entry->title,
                votes: $votes,
                position: $position,
            );
        }

        return $results;
    }
}
