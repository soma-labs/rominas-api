<?php

declare(strict_types=1);

namespace Rominas\Editions\Actions;

use Rominas\Editions\DataTransferObjects\EditionData;
use Rominas\Editions\Model\Edition;

class UpdateEditionAction
{
    public function execute(Edition $edition, EditionData $data): Edition
    {
        $edition->name = $data->name;

        if ($data->slug !== null) {
            $edition->slug = $data->slug;
        }

        $edition->starts_at = $data->starts_at;
        $edition->nominations_start_at = $data->nominations_start_at;
        $edition->nominations_end_at = $data->nominations_end_at;
        $edition->voting_start_at = $data->voting_start_at;
        $edition->voting_end_at = $data->voting_end_at;
        $edition->ends_at = $data->ends_at;

        // Weights are optional on the request; only overwrite when supplied so an update that omits
        // them keeps the edition's current weighting.
        if ($data->academy_vote_weight !== null) {
            $edition->academy_vote_weight = $data->academy_vote_weight;
        }

        if ($data->public_vote_weight !== null) {
            $edition->public_vote_weight = $data->public_vote_weight;
        }

        $edition->save();

        return $edition;
    }
}
