<?php

declare(strict_types=1);

namespace Rominas\Categories\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Categories\Model\Category;

class CategoryResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var Category $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'edition_id' => $category->edition_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'kind' => $category->kind->value,
            'kind_label' => $category->kind->label(),
            'nominee_type' => $category->nominee_type?->value,
            'nominee_type_label' => $category->nominee_type?->label(),
            'position' => $category->position,
            'description' => $category->description,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }
}
