<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Vote\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoteForSpecialEntryRequest extends FormRequest
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
            'entry_id' => 'required|integer|min:1',
        ];
    }

    public function entryId(): int
    {
        return (int) $this->validated()['entry_id'];
    }
}
