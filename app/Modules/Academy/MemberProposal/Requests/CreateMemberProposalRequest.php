<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMemberProposalRequest extends FormRequest
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
        // Duplicate-against-existing-member and duplicate-pending checks live in the action.
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'position' => 'sometimes|nullable|string',
            'company' => 'sometimes|nullable|string',
            'phone' => 'sometimes|nullable|string',
            'reason' => 'sometimes|nullable|string',
        ];
    }
}
