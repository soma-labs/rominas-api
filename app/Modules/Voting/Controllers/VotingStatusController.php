<?php

declare(strict_types=1);

namespace Rominas\Voting\Controllers;

use Rominas\Voting\Actions\ResolveVotingStatusAction;
use Rominas\Voting\Resources\VotingStatusResource;

/**
 * Public voting status — whether voting is upcoming, open or closed, with the window dates. No auth.
 */
class VotingStatusController
{
    public function __invoke(ResolveVotingStatusAction $action): VotingStatusResource
    {
        return new VotingStatusResource($action->execute());
    }
}
