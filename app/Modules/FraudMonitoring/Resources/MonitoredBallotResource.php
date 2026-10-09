<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Voting\Model\Ballot;

/**
 * A ballot as seen in the fraud-review UI: its lifecycle, the pseudonymized hashes (never plaintext), a
 * duplicate-`ip_hash` fraud signal, the e-mail validation verdict on its address (null = not checked),
 * and — if cancelled — the batch it belongs to and that batch's reason.
 */
class MonitoredBallotResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var Ballot $ballot */
        $ballot = $this->resource;

        $batch = $ballot->invalidationBatch;

        return [
            'id' => $ballot->id,
            'edition_id' => $ballot->edition_id,
            'status' => $ballot->status->value,
            'submitted_at' => $ballot->submitted_at?->toISOString(),
            'email_hash' => $ballot->email_hash,
            'ip_hash' => $ballot->ip_hash,
            'ip_hash_shared_count' => (int) ($ballot->getAttribute('ip_hash_shared_count') ?? 1),
            'email_check_status' => $ballot->email_check_status,
            'email_check_sub_status' => $ballot->email_check_sub_status,
            'email_check_decision' => $ballot->email_check_decision?->value,
            'invalidated' => $ballot->invalidation_batch_id !== null,
            'invalidation_batch_id' => $ballot->invalidation_batch_id,
            'invalidation_reason' => $batch?->reason,
        ];
    }
}
