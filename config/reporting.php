<?php

declare(strict_types=1);

use Rominas\Reporting\Reports\CancelledVotesPerDayReport;
use Rominas\Reporting\Reports\EditionSummaryReport;
use Rominas\Reporting\Reports\NominationsPerEntityReport;
use Rominas\Reporting\Reports\PublicVotesPerEntityReport;
use Rominas\Reporting\Reports\VotesPerCategoryPerDayReport;

return [
    /*
    |--------------------------------------------------------------------------
    | Registered reports
    |--------------------------------------------------------------------------
    |
    | The reports the ReportRegistry exposes, each an implementation of
    | Rominas\Reporting\Reports\ReportInterface. They are indexed by their
    | key() and served by the /admin/reports endpoints (view + CSV/Excel export).
    | Add a report class here to make it available; remove it to retire it.
    |
    */

    'reports' => [
        EditionSummaryReport::class,
        VotesPerCategoryPerDayReport::class,
        NominationsPerEntityReport::class,
        PublicVotesPerEntityReport::class,
        CancelledVotesPerDayReport::class,
    ],
];
