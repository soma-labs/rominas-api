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
     * are dynamic (they depend on the shortlist) and live in SubmitBallotAction. No `distinct` on the nominees:
     * Laravel applies it across every category, and one nominee may be ranked in several categories.
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
            'categories.*.nominees.*' => 'integer',
        ];
    }
}
