<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NomineeSuggestionsRequest extends FormRequest
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
            'search' => 'required|string|min:3|max:255',
        ];
    }

    public function searchTerm(): string
    {
        return trim((string) $this->validated()['search']);
    }
}
