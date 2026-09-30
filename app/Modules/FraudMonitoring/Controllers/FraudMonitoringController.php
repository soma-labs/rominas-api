<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Actions\InvalidateBallotsAction;
use Rominas\FraudMonitoring\Actions\ListMonitoredBallotsAction;
use Rominas\FraudMonitoring\Factories\InvalidateBallotsDataFactory;
use Rominas\FraudMonitoring\Model\InvalidationBatch;
use Rominas\FraudMonitoring\QueryBuilders\InvalidationBatchQueryBuilder;
use Rominas\FraudMonitoring\Requests\InvalidateBallotsRequest;
use Rominas\FraudMonitoring\Requests\ListMonitoredBallotsRequest;
use Rominas\FraudMonitoring\Resources\InvalidationBatchResource;
use Rominas\FraudMonitoring\Resources\MonitoredBallotResource;
use Rominas\Users\Model\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-facing fraud monitoring for an edition: review its public ballots (with a duplicate-IP signal)
 * and cancel fraudulent votes in audited batches. Authorization is the `fraudMonitoring` permission via
 * InvalidationBatchPolicy (the `fraud_monitor` and `custodian` roles).
 */
class FraudMonitoringController
{
    /**
     * The edition's submitted ballots, paginated, each with its shared-IP fraud signal and invalid state,
     * optionally filtered by ip_hash and cancelled/valid.
     */
    public function ballots(
        Edition $edition,
        ListMonitoredBallotsRequest $request,
        ListMonitoredBallotsAction $action,
    ): AnonymousResourceCollection {
        return MonitoredBallotResource::collection($action->execute($edition, $request->filters()));
    }

    /**
     * The edition's vote-cancellation batches (audit log).
     */
    public function index(Edition $edition, Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return InvalidationBatchResource::collection(
            InvalidationBatch::query()
                ->forEdition($edition)
                ->visibleToUser($user)
                ->withCount('ballots')
                ->orderByDesc('id')
                ->paginate(InvalidationBatchQueryBuilder::PER_PAGE),
        );
    }

    /**
     * Cancel the selected ballots as one batch, with a mandatory reason.
     */
    public function store(
        Edition $edition,
        InvalidateBallotsRequest $request,
        InvalidateBallotsAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $batch = $action->execute($edition, InvalidateBallotsDataFactory::fromRequest($request), $user);

        return (new InvalidationBatchResource($batch))->response()->setStatusCode(201);
    }

    /**
     * A single cancellation batch.
     */
    public function show(Edition $edition, InvalidationBatch $invalidationBatch): InvalidationBatchResource
    {
        abort_if($invalidationBatch->edition_id !== $edition->id, Response::HTTP_NOT_FOUND);

        return InvalidationBatchResource::make($invalidationBatch->loadCount('ballots'));
    }
}
