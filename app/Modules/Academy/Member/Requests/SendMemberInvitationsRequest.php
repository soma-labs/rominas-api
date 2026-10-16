<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Member\Support\InvitationTemplates;
use Rominas\Users\Model\User;

/**
 * Invitations sent to members the admin picked, with a chosen template. Only members visible to the admin
 * who are awaiting an invitation or already invited ({@see MemberStatus::invitable()}) can be picked.
 */
class SendMemberInvitationsRequest extends FormRequest
{
    public const int MAX_MEMBERS = 500;

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
            'member_ids' => 'required|array|min:1|max:' . self::MAX_MEMBERS,
            'member_ids.*' => 'required|integer|distinct',
            'template' => ['required', 'string', Rule::in(InvitationTemplates::keys())],
        ];
    }

    /**
     * Every picked member must exist, be visible to the admin and be awaiting an invitation or invited.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('member_ids') || $validator->errors()->has('member_ids.*')) {
                    return;
                }

                /** @var User $user */
                $user = $this->user();

                /** @var list<int> $memberIds */
                $memberIds = $this->input('member_ids');

                $statuses = Member::query()
                    ->visibleToUser($user)
                    ->whereIn('id', $memberIds)
                    ->pluck('status', 'id');

                foreach ($memberIds as $index => $memberId) {
                    $status = $statuses->get($memberId);

                    if ($status === null) {
                        $validator->errors()->add("member_ids.{$index}", __('The selected member does not exist.'));
                    } elseif (! in_array($status, MemberStatus::invitable(), true)) {
                        $validator->errors()->add("member_ids.{$index}", __('This member has already joined or is suspended.'));
                    }
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function memberIds(): array
    {
        /** @var list<int|string> $memberIds */
        $memberIds = $this->validated('member_ids');

        return array_map(intval(...), $memberIds);
    }

    public function template(): string
    {
        return (string) $this->validated('template');
    }
}
