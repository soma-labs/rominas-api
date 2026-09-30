<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

/**
 * One row of the fraud monitor's edition picker: the edition, whether cancelling votes is still allowed
 * (not once results are published), and its ballot / alert counts (set by ListFraudMonitoringEditionsAction).
 */
class FraudMonitoringEditionResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var Edition $edition */
        $edition = $this->resource;

        return [
            'id' => $edition->id,
            'name' => $edition->name,
            'slug' => $edition->slug,
            'status' => $edition->status->value,
            'status_label' => $edition->status->label(),
            'cancellation_allowed' => ! in_array($edition->status, [EditionStatus::ResultsPublished, EditionStatus::Archived], strict: true),
            'submitted_ballots_count' => (int) $edition->getAttribute('submitted_ballots_count'),
            'cancelled_ballots_count' => (int) $edition->getAttribute('cancelled_ballots_count'),
            'pending_alerts_count' => (int) $edition->getAttribute('pending_alerts_count'),
        ];
    }
}
