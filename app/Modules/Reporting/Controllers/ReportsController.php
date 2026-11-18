<?php

declare(strict_types=1);

namespace Rominas\Reporting\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Rominas\Reporting\Enums\ReportFormat;
use Rominas\Reporting\Exporters\SpreadsheetReportExporter;
use Rominas\Reporting\Factories\ReportParametersFactory;
use Rominas\Reporting\Reports\ReportInterface;
use Rominas\Reporting\Requests\RunReportRequest;
use Rominas\Reporting\Resources\ReportResource;
use Rominas\Reporting\Support\ReportRegistry;

/**
 * Management-facing reporting: browse the available reports, view one as a table, or download it as CSV
 * or Excel. Every report is resolved by its key from the {@see ReportRegistry}; authorization is the
 * `reporting` permission enforced by the route's `can:reporting` middleware.
 */
class ReportsController
{
    /**
     * The catalogue: every registered report's key, title and columns (no data).
     */
    public function index(Request $request, ReportRegistry $registry): AnonymousResourceCollection
    {
        $includeResultsData = ReportParametersFactory::viewerMaySeeResults($request);

        return ReportResource::collection(array_map(
            static fn(ReportInterface $report): ReportResource => ReportResource::make($report)->withResultsData($includeResultsData),
            array_values($registry->all()),
        ));
    }

    /**
     * Run a report and return its rows as tabular JSON.
     */
    public function show(string $report, RunReportRequest $request, ReportRegistry $registry): ReportResource
    {
        $resolved = $registry->resolve($report);

        abort_if($resolved === null, 404);

        $parameters = ReportParametersFactory::fromRequest($request);

        return ReportResource::make($resolved)
            ->withResultsData($parameters->includeResultsData)
            ->withRows($resolved->rows($parameters));
    }

    /**
     * Stream a report as a downloadable CSV (default) or XLSX file.
     */
    public function export(
        string $report,
        RunReportRequest $request,
        ReportRegistry $registry,
        SpreadsheetReportExporter $exporter,
    ): StreamedResponse {
        $resolved = $registry->resolve($report);

        abort_if($resolved === null, 404);

        $parameters = ReportParametersFactory::fromRequest($request);
        $format = ReportFormat::from($request->validated('format', ReportFormat::Csv->value));

        return $exporter->stream($resolved, $parameters, $format);
    }
}
