<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNominationFollowUpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('members', 'id'),
            ],
            'template' => [
                'required',
                'string',
                Rule::in(['ro', 'en']),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function memberIds(): array
    {
        return array_map('intval', $this->validated('member_ids'));
    }

    public function template(): string
    {
        return $this->validated('template');
    }
}
