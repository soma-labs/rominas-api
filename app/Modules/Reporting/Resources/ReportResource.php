<?php

declare(strict_types=1);

namespace Rominas\Reporting\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Reporting\Reports\ReportInterface;

/**
 * Renders a report as tabular JSON: its identity and column headings, plus — when the report has been
 * run via {@see withRows()} — the data rows. The catalogue endpoint returns these without `rows`; the
 * view endpoint includes them. The same `columns`/`rows` shape mirrors what the spreadsheet export writes.
 *
 * @property-read ReportInterface $resource
 */
class ReportResource extends JsonResource
{
    /**
     * @var iterable<int, array<string, string|int|float|null>>|null
     */
    private ?iterable $rows = null;

    private bool $includeResultsData = false;

    /**
     * Include the columns that reveal results, for a viewer holding the `results` permission.
     */
    public function withResultsData(bool $includeResultsData): static
    {
        $this->includeResultsData = $includeResultsData;

        return $this;
    }

    /**
     * Attach the report's data rows (from {@see ReportInterface::rows()}) so they are included in the
     * output. Without this the resource renders metadata only.
     *
     * @param  iterable<int, array<string, string|int|float|null>>  $rows
     */
    public function withRows(iterable $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        $report = $this->resource;

        return [
            'key' => $report->key(),
            'title' => $report->title(),
            'columns' => $report->columns($this->includeResultsData),
            'category_kinds' => array_map(static fn(CategoryKind $kind): string => $kind->value, $report->categoryFilterKinds()),
            'rows' => $this->when($this->rows !== null, fn(): array => $this->rowsToArray()),
        ];
    }

    /**
     * @return list<array<string, string|int|float|null>>
     */
    private function rowsToArray(): array
    {
        $rows = [];

        foreach ($this->rows ?? [] as $row) {
            $rows[] = $row;
        }

        return $rows;
    }
}
