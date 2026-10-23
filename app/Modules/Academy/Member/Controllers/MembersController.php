<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Rominas\Academy\Actions\SendAcademyInvitationsAction;
use Rominas\Academy\Actions\SendSelectedMemberInvitationFollowUpsAction;
use Rominas\Academy\Actions\SendSelectedMemberInvitationsAction;
use Rominas\Academy\Member\Actions\CreateMemberAction;
use Rominas\Academy\Member\Actions\DeleteMemberAction;
use Rominas\Academy\Member\Actions\ExportMembersAction;
use Rominas\Academy\Member\Actions\ImportMembersAction;
use Rominas\Academy\Member\Actions\InviteMemberAction;
use Rominas\Academy\Member\Actions\UpdateMemberAction;
use Rominas\Academy\Member\Factories\MemberDataFactory;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Member\QueryBuilders\MemberQueryBuilder;
use Rominas\Academy\Member\Requests\CreateMemberRequest;
use Rominas\Academy\Member\Requests\ImportMembersRequest;
use Rominas\Academy\Member\Requests\ListMembersRequest;
use Rominas\Academy\Member\Requests\SendInvitationFollowUpsRequest;
use Rominas\Academy\Member\Requests\SendMemberInvitationsRequest;
use Rominas\Academy\Member\Requests\SendNominationFollowUpsRequest;
use Rominas\Academy\Member\Requests\UpdateMemberRequest;
use Rominas\Academy\Member\Resources\AdminMemberResource;
use Rominas\Academy\Member\Resources\InvitationTemplateResource;
use Rominas\Academy\Member\Resources\MemberImportResultResource;
use Rominas\Academy\Member\Support\InvitationTemplates;
use Rominas\Users\Model\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Rominas\Editions\Model\Edition;
use Rominas\Academy\Actions\SendSelectedMemberNominationFollowUpsAction;

use function response;

/**
 * Admin-side management of the academy roster (guarded by `auth:sanctum` + the `members` permission).
 */
class MembersController
{
    public function index(ListMembersRequest $request): AnonymousResourceCollection
    {
        return AdminMemberResource::collection(
            $this->membersQuery($request)->paginate(
                $request->integer('perPage', MemberQueryBuilder::PER_PAGE),
            ),
        );
    }

    public function show(Member $member): AdminMemberResource
    {
        return new AdminMemberResource($member);
    }

    public function create(CreateMemberRequest $request, CreateMemberAction $action): JsonResponse
    {
        $member = $action->execute(MemberDataFactory::fromCreateRequest($request));

        return (new AdminMemberResource($member))->response()->setStatusCode(201);
    }

    /**
     * Create a member awaiting invitation for each usable row of an uploaded CSV / XLSX list, all of them
     * founding members or none.
     */
    public function import(ImportMembersRequest $request, ImportMembersAction $action): MemberImportResultResource
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        return new MemberImportResultResource($action->execute(
            file: $file,
            isFoundingMember: $request->boolean('is_founding_member'),
        ));
    }

    public function update(Member $member, UpdateMemberRequest $request, UpdateMemberAction $action): AdminMemberResource
    {
        $member = $action->execute($member, MemberDataFactory::fromUpdateRequest($request, $member));

        return new AdminMemberResource($member);
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
    public function invite(Member $member, InviteMemberAction $action): JsonResponse
    {
        $action->execute($member);

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

    /**
     * Send the invitation, in the chosen template, to each picked member still awaiting one, and move
     * them to `invited`. One e-mail per member.
     */
    public function invitePicked(SendMemberInvitationsRequest $request, SendSelectedMemberInvitationsAction $action): JsonResponse
    {
        $invited = $action->execute($request->memberIds(), $request->template());

        return response()->json(['success' => true, 'invited' => $invited]);
    }

    /**
     * The invitation templates an admin can choose from, in config/academy-invitations.php's order.
     */
    public function invitationTemplates(): AnonymousResourceCollection
    {
        return InvitationTemplateResource::collection(InvitationTemplates::all());
    }

    /**
     * The roster filtered as the list request asks, with each member's progress in the active edition.
     */
    private function membersQuery(ListMembersRequest $request): MemberQueryBuilder
    {
        /** @var User $user */
        $user = $request->user();

        $edition = Edition::query()->active()->first();
        $startedNominations = $request->startedNominations();
        $submittedNominations = $request->submittedNominations();
        $submittedMemberProposals = $request->submittedMemberProposals();
        $foundingMember = $request->foundingMember();

        return Member::query()
            ->visibleToUser($user)
            ->search($request->query('search'))
            ->when(
                $request->statuses(),
                fn(MemberQueryBuilder $query, array $statuses): MemberQueryBuilder => $query->filterByStatuses(...$statuses),
            )
            ->when(
                $startedNominations !== null,
                fn(MemberQueryBuilder $query): MemberQueryBuilder => $query->filterByStartedNominations((bool) $startedNominations, $edition),
            )
            ->when(
                $submittedNominations !== null,
                fn(MemberQueryBuilder $query): MemberQueryBuilder => $query->filterBySubmittedNominations((bool) $submittedNominations, $edition),
            )
            ->when(
                $submittedMemberProposals !== null,
                fn(MemberQueryBuilder $query): MemberQueryBuilder => $query->filterBySubmittedMemberProposals((bool) $submittedMemberProposals),
            )
            ->when(
                $foundingMember !== null,
                fn(MemberQueryBuilder $query): MemberQueryBuilder => $query->filterByFoundingMember((bool) $foundingMember),
            )
            ->withMemberProgress($edition)
            ->orderBy(
                $request->query('orderBy', 'id'),
                $request->query('orderDir', 'desc'),
            );
    }

    public function export(
        ListMembersRequest $request,
        ExportMembersAction $action,
    ): BinaryFileResponse {
        return $action->execute(
            $this->membersQuery($request),
        );
    }

    public function sendNominationFollowUps(
        SendNominationFollowUpsRequest $request,
        SendSelectedMemberNominationFollowUpsAction $action,
    ): JsonResponse {
        $sent = $action->execute(
            $request->memberIds(),
            $request->template(),
        );

        return response()->json([
            'success' => true,
            'sent' => $sent,
        ]);
    }

    public function sendInvitationFollowUps(
        SendInvitationFollowUpsRequest $request,
        SendSelectedMemberInvitationFollowUpsAction $action,
    ): JsonResponse {
        $sent = $action->execute(
            $request->memberIds(),
            $request->template(),
        );

        return response()->json([
            'success' => true,
            'sent' => $sent,
        ]);
    }
}
