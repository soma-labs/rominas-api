<?php

declare(strict_types=1);

namespace Rominas\Voting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitBallotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Structural validation only. Per-category rules — a category must exist in the edition, and the picks
     * must be exactly N distinct shortlisted nominees in order (N = 3, or the shortlist size if smaller) —
     * are dynamic (they depend on the shortlist) and live in SubmitBallotAction.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'categories' => 'required|array|min:1',
            'categories.*.category_id' => 'required|integer',
            'categories.*.nominees' => 'required|array|min:1|max:3',
            'categories.*.nominees.*' => 'integer|distinct',
        ];
    }
}
