<?php

declare(strict_types=1);

namespace Rominas\Reporting\Exporters;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Rominas\Reporting\DataTransferObjects\ReportParameters;
use Rominas\Reporting\Enums\ReportFormat;
use Rominas\Reporting\Reports\ReportInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams any {@see ReportInterface} to the browser as a downloadable CSV or XLSX file. Both formats are
 * driven from the report's `columns()`/`rows()` via openspout, written row-by-row straight to the output
 * stream so memory stays flat regardless of report size.
 */
class SpreadsheetReportExporter
{
    public function stream(ReportInterface $report, ReportParameters $parameters, ReportFormat $format): StreamedResponse
    {
        $filename = "{$report->key()}.{$format->value}";

        return response()->streamDownload(function () use ($report, $parameters, $format): void {
            $writer = $this->writerFor($format);
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues($report->columns($parameters->includeResultsData)));

            foreach ($report->rows($parameters) as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }

            $writer->close();
        }, $filename, ['Content-Type' => $format->contentType()]);
    }

    private function writerFor(ReportFormat $format): WriterInterface
    {
        return match ($format) {
            ReportFormat::Csv => new CsvWriter(),
            ReportFormat::Xlsx => new XlsxWriter(),
        };
    }
}
