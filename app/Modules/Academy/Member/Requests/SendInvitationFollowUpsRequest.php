<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;

class SendInvitationFollowUpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_ids' => ['required', 'array', 'min:1', 'max:500'],
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

    public function memberIds(): array
    {
        return array_map('intval', $this->validated('member_ids'));
    }

    public function template(): string
    {
        return $this->validated('template');
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $memberIds = $this->input('member_ids', []);

                $members = Member::query()
                    ->visibleToUser($this->user())
                    ->whereIn('id', $memberIds)
                    ->get()
                    ->keyBy('id');

                foreach ($memberIds as $index => $memberId) {
                    $member = $members->get($memberId);

                    if (! $member || $member->status !== MemberStatus::Invited) {
                        $validator->errors()->add(
                            "member_ids.{$index}",
                            'The selected member must have invited status.'
                        );
                    }
                }
            },
        ];
    }
}
