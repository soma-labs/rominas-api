<?php

declare(strict_types=1);

namespace Rominas\Voting\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Voting\Actions\ResolveBallotByTokenAction;
use Rominas\Voting\Actions\SubmitBallotAction;
use Rominas\Voting\Factories\BallotSubmissionDataFactory;
use Rominas\Voting\Requests\SubmitBallotRequest;
use Rominas\Voting\Resources\BallotResource;

use function response;

/**
 * Public, accountless ballot endpoints. The one-time link token (query/body) is the authorization; an
 * invalid, expired, or already-used token yields a generic 422.
 */
class BallotController
{
    /**
     * Load the ballot a token authorizes: the edition's shortlist to rank, grouped by category.
     */
    public function show(Request $request, ResolveBallotByTokenAction $resolve): BallotResource
    {
        $ballot = $resolve->execute((string) $request->query('token', ''));

        $shortlist = ShortlistEntry::query()
            ->forEdition($ballot->edition)
            ->with(['nominee', 'category'])
            ->orderBy('category_id')
            ->orderBy('position')
            ->get();

        return new BallotResource($ballot, $shortlist);
    }

    /**
     * Cast the ballot (one-shot). The link is consumed on success.
     */
    public function submit(SubmitBallotRequest $request, SubmitBallotAction $action): JsonResponse
    {
        $action->execute(BallotSubmissionDataFactory::fromRequest($request));

        return response()->json([
            'success' => true,
            'message' => __('Your vote has been recorded. Thank you!'),
        ]);
    }
}
