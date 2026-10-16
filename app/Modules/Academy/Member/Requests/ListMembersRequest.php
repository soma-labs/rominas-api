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
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(MemberStatus::class)],
        ];
    }

    /**
     * `?status=invited` and `?status[]=invited&status[]=active` both work: one value becomes a list.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->query('status'))) {
            $this->merge(['status' => [$this->query('status')]]);
        }
    }

    /**
     * The statuses to keep; empty when the list is not filtered by status.
     *
     * @return list<MemberStatus>
     */
    public function statuses(): array
    {
        /** @var list<string> $statuses */
        $statuses = $this->validated('status', []);

        return array_map(MemberStatus::from(...), $statuses);
    }
}
