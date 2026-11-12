<?php

declare(strict_types=1);

namespace Rominas\Voting\Controllers;

use Illuminate\Http\JsonResponse;
use Rominas\Voting\Actions\RequestVotingLinkAction;
use Rominas\Voting\Requests\RequestVotingLinkRequest;

use function response;

/**
 * Public, accountless voting endpoints. No auth — the one-time token is the authorization.
 */
class VotingController
{
    /**
     * Request a one-time voting link. A well-formed address always gets the same 200, at once — the
     * response never reveals whether it has already requested/voted or whether e-mail validation rejects
     * it (validation, the ballot and the link e-mail happen in a queued job). Only a closed voting window
     * surfaces, as a 422 from the resolve gate.
     */
    public function request(RequestVotingLinkRequest $request, RequestVotingLinkAction $action): JsonResponse
    {
        $action->execute($request->validated()['email']);

        return response()->json([
            'success' => true,
            'message' => __('If the address is valid, you will receive a voting link.'),
        ]);
    }
}
