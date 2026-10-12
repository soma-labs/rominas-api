<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin rejection of a proposed entry. The note is required: the member who proposed it reads it.
 */
class RejectSpecialEntryRequest extends FormRequest
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
            'note' => 'required|string|max:2000',
        ];
    }
}
