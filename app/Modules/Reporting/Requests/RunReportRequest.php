<?php

declare(strict_types=1);

namespace Rominas\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Rominas\Reporting\Enums\ReportFormat;

/**
 * Validates the query inputs shared by the report view and export endpoints: an optional `edition_id`
 * (defaults to the active edition), an optional `format` (export only), and an optional inclusive date
 * window. Authorization is handled by the route's `can:reporting` middleware.
 */
class RunReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_id' => ['sometimes', 'integer', 'exists:editions,id'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'format' => ['sometimes', 'string', 'in:' . ReportFormat::Csv->value . ',' . ReportFormat::Xlsx->value],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }
}
