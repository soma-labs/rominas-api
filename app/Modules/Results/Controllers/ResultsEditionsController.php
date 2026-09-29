<?php

declare(strict_types=1);

namespace Rominas\Results\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Rominas\Editions\Model\Edition;
use Rominas\Results\Resources\ResultsEditionResource;

/**
 * The custodian's edition picker. Lives under the `results` permission (not `editions`) because a
 * custodian holds only `results`, so it can't call the Editions index to find an edition to review.
 */
class ResultsEditionsController
{
    public function index(): AnonymousResourceCollection
    {
        $editions = Edition::query()
            ->withResultsAvailable()
            ->with('resultSnapshot')
            ->latest('starts_at')
            ->get();

        return ResultsEditionResource::collection($editions);
    }
}
