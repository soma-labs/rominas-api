<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

/**
 * Resolves the single active edition that members may nominate in, enforcing the deadline gate:
 * nominations are open only when that edition's status is `nominations_open` AND now falls within
 * `[nominations_start_at, nominations_end_at]`. Throws a 422 otherwise.
 */
class ResolveOpenNominationEditionAction
{
    public function execute(): Edition
    {
        $edition = Edition::query()->active()->first();

        if ($edition === null || $edition->status !== EditionStatus::NominationsOpen) {
            throw ValidationException::withMessages([
                'nominations' => __('Nominations are not open.'),
            ]);
        }

        $now = now();

        if ($now->lt($edition->nominations_start_at) || $now->gt($edition->nominations_end_at)) {
            throw ValidationException::withMessages([
                'nominations' => __('The nomination window is closed.'),
            ]);
        }

        return $edition;
    }
}
