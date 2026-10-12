<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The text of a special-category entry: a member's proposal, an admin's correction of it, or the result
 * an admin enters for an appointed category.
 */
class SaveSpecialEntryRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
        ];
    }
}
