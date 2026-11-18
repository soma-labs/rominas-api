<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;

/**
 * How many member votes each entry of a propose-or-vote special category has, per category, most first.
 * Counts the same way as the special results: only approved entries are listed (a merged entry's votes
 * already sit on its target; pending and rejected entries never count), including those without votes.
 * Appointed categories are left out, as they have no votes. The optional `from`/`to` window applies to
 * when each vote was cast. In these categories the vote count is the result itself, so this report
 * shows the result to everyone who may use reporting, before publication too.
 */
final class SpecialVotesPerEntryReport implements ReportInterface
{
    public function key(): string
    {
        return 'special-votes-per-entry';
    }

    public function title(): string
    {
        return 'Special Award Votes per Entry';
    }

    public function columns(): array
    {
        return ['category', 'entry', 'votes'];
    }

    public function rows(ReportParameters $parameters): iterable
    {
        return SpecialEntry::query()
            ->forEdition($parameters->edition)
            ->forCategoryKind(CategoryKind::ProposeOrVote)
            ->approved()
            ->with('category:id,name,position')
            ->withCount(['votes' => function (Builder $query) use ($parameters): void {
                if ($parameters->from !== null) {
                    $query->where('created_at', '>=', $parameters->from);
                }

                if ($parameters->to !== null) {
                    $query->where('created_at', '<=', $parameters->to);
                }
            }])
            ->get()
            ->sort(static fn(SpecialEntry $a, SpecialEntry $b): int => [$a->category->position, $a->category_id, (int) $b->votes_count, $a->title]
                <=> [$b->category->position, $b->category_id, (int) $a->votes_count, $b->title])
            ->map(static fn(SpecialEntry $entry): array => [
                'category' => $entry->category->name,
                'entry' => $entry->title,
                'votes' => (int) $entry->votes_count,
            ])
            ->values()
            ->all();
    }
}
