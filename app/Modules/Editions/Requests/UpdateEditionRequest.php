<?php

declare(strict_types=1);

namespace Rominas\Editions\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Rominas\Editions\Model\Edition;

class UpdateEditionRequest extends FormRequest
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
        /** @var Edition $edition */
        $edition = $this->route('edition');

        // `after:` is strict (>), so chaining each field after the previous enforces
        // starts_at < nominations_start_at < nominations_end_at < voting_start_at
        // < voting_end_at < ends_at.
        return [
            'name' => ['required', 'string', Rule::unique($edition->getTable(), 'name')->ignore($edition)],
            // Optional — omit to keep the edition's current slug.
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique($edition->getTable(), 'slug')->ignore($edition),
            ],
            'starts_at' => 'required|date',
            'nominations_start_at' => 'required|date|after:starts_at',
            'nominations_end_at' => 'required|date|after:nominations_start_at',
            'voting_start_at' => 'required|date|after:nominations_end_at',
            'voting_end_at' => 'required|date|after:voting_start_at',
            'ends_at' => 'required|date|after:voting_end_at',
            // Result weighting (Scoring). Optional — omit to keep the edition's current weights.
            'academy_vote_weight' => 'sometimes|integer|min:0|max:100',
            'public_vote_weight' => 'sometimes|integer|min:0|max:100',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // The final score divides by the sum of the two weights, so they must not both be zero.
        // Consider the effective weights: a value omitted from the request keeps the edition's current one.
        $validator->after(function (Validator $validator): void {
            /** @var Edition $edition */
            $edition = $this->route('edition');

            $academy = $this->has('academy_vote_weight') ? (int) $this->input('academy_vote_weight') : $edition->academy_vote_weight;
            $public = $this->has('public_vote_weight') ? (int) $this->input('public_vote_weight') : $edition->public_vote_weight;

            if ($academy === 0 && $public === 0) {
                $validator->errors()->add('academy_vote_weight', 'The academy and public vote weights cannot both be zero.');
            }
        });
    }
}
