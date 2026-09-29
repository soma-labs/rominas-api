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
     * Request a one-time voting link. Always 200 — the response never reveals whether the address is
     * eligible or has already requested/voted (a link is only actually issued for a new email while
     * voting is open). A closed voting window surfaces as a 422 from the resolve gate.
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
