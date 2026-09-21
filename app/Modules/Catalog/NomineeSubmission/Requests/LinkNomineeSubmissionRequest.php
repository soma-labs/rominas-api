<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Link a pending submission to an existing Catalog entity of its type. Existence of the id in the correct
 * table is checked in the action (which has the submission's type in hand).
 */
class LinkNomineeSubmissionRequest extends FormRequest
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
            'nominee_id' => 'required|integer',
            'note' => 'sometimes|nullable|string',
        ];
    }
}
