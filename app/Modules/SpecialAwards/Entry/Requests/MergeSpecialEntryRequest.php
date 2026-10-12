<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin merge of a duplicate entry into the entry that stays.
 */
class MergeSpecialEntryRequest extends FormRequest
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
            'target_entry_id' => 'required|integer|min:1',
        ];
    }

    public function targetEntryId(): int
    {
        return (int) $this->validated()['target_entry_id'];
    }
}
