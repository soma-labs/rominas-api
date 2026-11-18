<?php

declare(strict_types=1);

namespace Rominas\Reporting\Reports;

use Rominas\Reporting\DataTransferObjects\ReportParameters;

/**
 * One report the client can view as a table or download as CSV/Excel. A report exposes its data once,
 * format-agnostically: metadata (`key`/`title`/`columns`) is parameter-free so the catalogue can list
 * reports without running them, while `rows()` runs the query for a given {@see ReportParameters}. The
 * same `columns()`/`rows()` shape feeds both the tabular JSON resource and the spreadsheet exporter.
 *
 * Implementations are registered by class in `config/reporting.php` and resolved by `key()` through the
 * {@see \Rominas\Reporting\Support\ReportRegistry}.
 */
interface ReportInterface
{
    /**
     * Stable machine key used in the URL and the registry (e.g. `votes-per-category-per-day`).
     */
    public function key(): string;

    /**
     * Human-readable report title.
     */
    public function title(): string;

    /**
     * Ordered column headings — used both for the tabular JSON and the export header row. Each key
     * matches a key in every row returned by {@see rows()} for the same `$includeResultsData`. A report
     * may add columns that reveal results (custodian-only before publication) when `$includeResultsData`
     * is true, which it is only for viewers holding the `results` permission.
     *
     * @return list<string>
     */
    public function columns(bool $includeResultsData = false): array;

    /**
     * The report rows, each an ordered associative array keyed by column. Returned as an `iterable`
     * so large reports can be yielded and streamed to an export without materialising in memory.
     *
     * @return iterable<int, array<string, string|int|float|null>>
     */
    public function rows(ReportParameters $parameters): iterable;
}
