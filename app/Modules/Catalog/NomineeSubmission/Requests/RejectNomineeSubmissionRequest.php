<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reject a pending submission — an optional free-text reason.
 */
class RejectNomineeSubmissionRequest extends FormRequest
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
            'note' => 'sometimes|nullable|string',
        ];
    }
}
