<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Rominas\Academy\Member\Enums\MemberStatus;

class ListMembersRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::enum(MemberStatus::class)],
        ];
    }

    public function status(): ?MemberStatus
    {
        return MemberStatus::tryFrom((string) $this->validated('status'));
    }
}
