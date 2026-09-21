<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Materialize a new Catalog entity from a pending submission. `name` is optional — it defaults to the name
 * the member typed, and is provided only to correct spelling/casing before creating the canonical row.
 */
class CreateNomineeFromSubmissionRequest extends FormRequest
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
            'name' => 'sometimes|nullable|string|max:255',
            'note' => 'sometimes|nullable|string',
        ];
    }
}
