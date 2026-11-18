<?php

declare(strict_types=1);

namespace Rominas\Reporting\DataTransferObjects;

use Carbon\CarbonImmutable;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;

/**
 * The resolved inputs for running a report: the edition it covers and an optional inclusive date
 * window, an optional category (applied by the reports that have a category filter), and whether the viewer may see results data (the `results` permission). Built from a validated request by {@see \Rominas\Reporting\Factories\ReportParametersFactory}.
 * The `$from`/`$to` bounds are reserved for reports that filter by date; the first report ignores them.
 */
final class ReportParameters
{
    public function __construct(
        public readonly Edition $edition,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
        public readonly bool $includeResultsData = false,
        public readonly ?Category $category = null,
    ) {}
}
