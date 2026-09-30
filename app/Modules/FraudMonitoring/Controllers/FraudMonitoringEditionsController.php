<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Rominas\FraudMonitoring\Actions\ListFraudMonitoringEditionsAction;
use Rominas\FraudMonitoring\Resources\FraudMonitoringEditionResource;

/**
 * The fraud monitor's edition picker. Lives under the `fraudMonitoring` permission (not `editions`) because
 * a fraud monitor doesn't hold `editions`, so it can't call the Editions index to find an edition to review.
 */
class FraudMonitoringEditionsController
{
    public function index(ListFraudMonitoringEditionsAction $action): AnonymousResourceCollection
    {
        return FraudMonitoringEditionResource::collection($action->execute());
    }
}
