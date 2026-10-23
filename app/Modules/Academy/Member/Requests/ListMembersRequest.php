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
            'startedNominations' => ['nullable', Rule::in(['yes', 'no'])],
            'submittedNominations' => ['nullable', Rule::in(['yes', 'no'])],
            'submittedMemberProposals' => ['nullable', Rule::in(['yes', 'no'])],
            'foundingMember' => ['nullable', Rule::in(['yes', 'no'])],
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

    /**
     * Whether to keep the members who started (true) or did not start (false) their nominations in the
     * active edition; null when the list is not filtered by it.
     */
    public function startedNominations(): ?bool
    {
        return $this->yesOrNo('startedNominations');
    }

    /**
     * Whether to keep the members who submitted (true) or did not submit (false) their nominations in
     * the active edition; null when the list is not filtered by it.
     */
    public function submittedNominations(): ?bool
    {
        return $this->yesOrNo('submittedNominations');
    }

    /**
     * Whether to keep the members who proposed someone (true) or nobody (false); null when the list is
     * not filtered by it.
     */
    public function submittedMemberProposals(): ?bool
    {
        return $this->yesOrNo('submittedMemberProposals');
    }

    /**
     * Whether to keep the founding members (true) or the others (false); null when the list is not
     * filtered by it.
     */
    public function foundingMember(): ?bool
    {
        return $this->yesOrNo('foundingMember');
    }

    private function yesOrNo(string $filter): ?bool
    {
        /** @var 'yes'|'no'|null $value */
        $value = $this->validated($filter);

        return $value === null ? null : $value === 'yes';
    }
}
