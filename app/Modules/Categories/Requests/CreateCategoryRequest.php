<?php

declare(strict_types=1);

namespace Rominas\Categories\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;

class CreateCategoryRequest extends FormRequest
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
        $isRanked = $this->input('kind', CategoryKind::Ranked->value) === CategoryKind::Ranked->value;

        return [
            'edition_id' => 'required|exists:' . Edition::class . ',id',
            'name' => [
                'required',
                'string',
                Rule::unique(Category::class, 'name')->where('edition_id', $this->input('edition_id')),
            ],
            'kind' => ['sometimes', Rule::enum(CategoryKind::class)],
            // Only a ranked category accepts a Catalog entity type.
            'nominee_type' => $isRanked ? ['required', Rule::enum(NomineeType::class)] : ['prohibited'],
            'position' => 'sometimes|integer',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
