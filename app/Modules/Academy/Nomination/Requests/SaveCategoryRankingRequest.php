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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Members nominate by typing names (free text); each name is staged for admin reconciliation into
        // a canonical Catalog entity. An empty array clears the category. `distinct` blocks exact repeats;
        // the action additionally rejects names that normalize to the same entry (e.g. differing only in case).
        return [
            'nominees' => 'present|array|max:5',
            'nominees.*' => 'string|distinct|min:1|max:255',
        ];
    }
}
