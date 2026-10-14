<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Academy\Member\DataTransferObjects\MemberImportResultData;
use Rominas\Academy\Member\DataTransferObjects\MemberImportSkippedRowData;

/**
 * The outcome of a member import: how many members were created, and each skipped row with its reason
 * (the row number is the spreadsheet's, header included).
 */
class MemberImportResultResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var MemberImportResultData $result */
        $result = $this->resource;

        return [
            'created' => $result->created,
            'skipped' => array_map(fn(MemberImportSkippedRowData $row): array => [
                'row' => $row->rowNumber,
                'email' => $row->email,
                'reason' => $row->reason,
            ], $result->skipped),
        ];
    }
}
