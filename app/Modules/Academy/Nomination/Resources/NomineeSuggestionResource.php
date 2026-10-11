<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Academy\Nomination\DataTransferObjects\NomineeSuggestionData;

class NomineeSuggestionResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var NomineeSuggestionData $suggestion */
        $suggestion = $this->resource;

        return [
            'id' => $suggestion->id,
            'name' => $suggestion->name,
        ];
    }
}
