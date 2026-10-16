<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Rominas\Academy\Member\DataTransferObjects\InvitationTemplateData;

/**
 * An invitation template an admin can choose from: its key (sent back on
 * `POST /api/admin/members/invitations/send`) and its label.
 */
class InvitationTemplateResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>|Arrayable<string, mixed>|JsonSerializable
     */
    public function toArray($request): array|JsonSerializable|Arrayable
    {
        /** @var InvitationTemplateData $template */
        $template = $this->resource;

        return [
            'key' => $template->key,
            'label' => $template->label,
        ];
    }
}
