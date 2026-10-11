<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCategoryRankingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A pick may be sent as a bare string (a typed name) — it is read as `{name, nominee_id: null}`.
     */
    protected function prepareForValidation(): void
    {
        $nominees = $this->input('nominees');

        if (! is_array($nominees)) {
            return;
        }

        $this->merge([
            'nominees' => array_map(
                static fn(mixed $pick): mixed => is_string($pick) ? ['name' => $pick, 'nominee_id' => null] : $pick,
                $nominees,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Each pick is a name, plus the Catalog entry's id when the member chose it from the suggestions
        // (linked at once). A name without an id is free text, staged for admin reconciliation into a
        // canonical Catalog entity. An empty array clears the category. Repeats are rejected by the action,
        // which knows that two Catalog entries may share a name while two typed names may not.
        return [
            'nominees' => 'present|array|max:5',
            'nominees.*' => 'array:name,nominee_id',
            'nominees.*.name' => 'required|string|max:255',
            'nominees.*.nominee_id' => 'nullable|integer|min:1',
        ];
    }
}
