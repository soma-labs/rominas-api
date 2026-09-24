<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Rominas\Academy\Actions\SendAcademyInvitationsAction;
use Rominas\Academy\Member\Actions\CreateMemberAction;
use Rominas\Academy\Member\Actions\DeleteMemberAction;
use Rominas\Academy\Member\Actions\UpdateMemberAction;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Factories\MemberDataFactory;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Member\QueryBuilders\MemberQueryBuilder;
use Rominas\Academy\Member\Requests\CreateMemberRequest;
use Rominas\Academy\Member\Requests\UpdateMemberRequest;
use Rominas\Academy\Member\Resources\MemberResource;
use Rominas\Auth\MagicLink\Jobs\SendMagicLinkJob;
use Rominas\Users\Model\User;

use function response;

/**
 * Admin-side management of the academy roster (guarded by `auth:sanctum` + the `members` permission).
 */
class MembersController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return MemberResource::collection(
            Member::query()
                ->visibleToUser($user)
                ->search($request->query('search'))
                ->orderBy(
                    $request->query('orderBy', 'name'),
                    $request->query('orderDir', 'asc'),
                )
                ->paginate($request->query('perPage', MemberQueryBuilder::PER_PAGE)),
        );
    }

    public function show(Member $member): MemberResource
    {
        return new MemberResource($member);
    }

    public function create(CreateMemberRequest $request, CreateMemberAction $action): JsonResponse
    {
        $member = $action->execute(MemberDataFactory::fromCreateRequest($request));

        return (new MemberResource($member))->response()->setStatusCode(201);
    }

    public function update(Member $member, UpdateMemberRequest $request, UpdateMemberAction $action): MemberResource
    {
        $member = $action->execute($member, MemberDataFactory::fromUpdateRequest($request, $member));

        return new MemberResource($member);
    }

    public function delete(Member $member, DeleteMemberAction $action): JsonResponse
    {
        $result = $action->execute($member);

        return response()->json(['success' => $result]);
    }

    /**
     * (Re)send a magic-link invitation to a single member. A member still awaiting their first
     * invitation moves to `Invited`; a re-send leaves an invited/active/suspended member's status alone.
     */
    public function invite(Member $member): JsonResponse
    {
        SendMagicLinkJob::dispatch('member', $member->email, 'academy-invitation-email');

        $member->forceFill([
            'status' => $member->status === MemberStatus::AwaitingInvitation
                ? MemberStatus::Invited
                : $member->status,
            'invited_at' => now(),
        ])->save();

        return response()->json(['success' => true]);
    }

    /**
     * Send a magic-link invitation to every member still awaiting one (`awaiting_invitation` status)
     * and move them to `invited`. Invitations are manual — never triggered by an edition transition.
     */
    public function inviteAll(SendAcademyInvitationsAction $action): JsonResponse
    {
        $invited = $action->execute();

        return response()->json(['success' => true, 'invited' => $invited]);
    }
}
