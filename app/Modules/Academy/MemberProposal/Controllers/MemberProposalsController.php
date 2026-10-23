<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Actions\CreateMemberProposalAction;
use Rominas\Academy\MemberProposal\Actions\WithdrawMemberProposalAction;
use Rominas\Academy\MemberProposal\Factories\MemberProposalDataFactory;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Academy\MemberProposal\QueryBuilders\MemberProposalQueryBuilder;
use Rominas\Academy\MemberProposal\Requests\CreateMemberProposalRequest;
use Rominas\Academy\MemberProposal\Resources\MemberProposalResource;

use function abort;
use function abort_unless;
use function response;

/**
 * Member-facing proposals (guard `member`). Ownership is implicit — a member only ever sees or acts on
 * their own proposals. Only a founding member may propose.
 */
class MemberProposalsController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $member = $this->member();

        return MemberProposalResource::collection(
            MemberProposal::query()
                ->forProposer($member)
                ->orderBy('created_at', 'desc')
                ->paginate($request->query('perPage', MemberProposalQueryBuilder::PER_PAGE)),
        );
    }

    public function create(CreateMemberProposalRequest $request, CreateMemberProposalAction $action): JsonResponse
    {
        $member = $this->member();

        abort_unless($member->is_founding_member, 403, __('Only founding members can propose new members.'));

        $proposal = $action->execute($member, MemberProposalDataFactory::fromCreateRequest($request));

        return (new MemberProposalResource($proposal))->response()->setStatusCode(201);
    }

    public function withdraw(MemberProposal $proposal, WithdrawMemberProposalAction $action): JsonResponse
    {
        if ($proposal->proposed_by_member_id !== $this->member()->id) {
            abort(403);
        }

        $result = $action->execute($proposal);

        return response()->json(['success' => $result]);
    }

    private function member(): Member
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return $member;
    }
}
