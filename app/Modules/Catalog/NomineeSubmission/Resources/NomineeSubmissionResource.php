<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;

class NomineeSubmissionResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var NomineeSubmission $submission */
        $submission = $this->resource;

        /** @var object{id: int, name: string, slug: string}|null $resolved */
        $resolved = $submission->resolvedNominee;

        return [
            'id' => $submission->id,
            'edition_id' => $submission->edition_id,
            'nominee_type' => $submission->nominee_type->value,
            'nominee_type_label' => $submission->nominee_type->label(),
            'raw_name' => $submission->raw_name,
            'normalized_name' => $submission->normalized_name,
            'status' => $submission->status->value,
            'status_label' => $submission->status->label(),
            'ranking_count' => $this->when($submission->rankings_count !== null, $submission->rankings_count),
            'resolved_nominee_id' => $submission->resolved_nominee_id,
            'resolved_nominee' => $resolved === null ? null : [
                'id' => $resolved->id,
                'name' => $resolved->name,
                'slug' => $resolved->slug,
            ],
            'suggestions' => $this->when($submission->suggestions !== null, $submission->suggestions),
            'review_note' => $submission->review_note,
            'reviewed_at' => $submission->reviewed_at,
            'created_at' => $submission->created_at,
            'updated_at' => $submission->updated_at,
        ];
    }
}
