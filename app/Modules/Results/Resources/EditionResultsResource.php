<?php

declare(strict_types=1);

namespace Rominas\Results\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Results\DataTransferObjects\EditionResults;
use Rominas\Results\Support\EditionResultsPresenter;

/**
 * Serializes an edition's results ({@see EditionResults}: the ranked and special categories, computed live
 * or rebuilt from a frozen snapshot, plus the source and publication time) into the results shape, enriched
 * with category and nominee display names by {@see EditionResultsPresenter}.
 */
class EditionResultsResource extends JsonResource
{
    private bool $withSpecialVotes = true;

    /**
     * Leave the member vote counts of the special categories out — for the public view.
     */
    public function withoutSpecialVotes(): static
    {
        $this->withSpecialVotes = false;

        return $this;
    }

    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var EditionResults $results */
        $results = $this->resource;

        return (new EditionResultsPresenter())->present($results, $this->withSpecialVotes);
    }
}
