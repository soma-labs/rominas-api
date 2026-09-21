<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Actions\CreateNomineeFromSubmissionAction;
use Rominas\Catalog\NomineeSubmission\Actions\LinkNomineeSubmissionAction;
use Rominas\Catalog\NomineeSubmission\Actions\RejectNomineeSubmissionAction;
use Rominas\Catalog\NomineeSubmission\Actions\SuggestCatalogMatchesAction;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Catalog\NomineeSubmission\QueryBuilders\NomineeSubmissionQueryBuilder;
use Rominas\Catalog\NomineeSubmission\Requests\CreateNomineeFromSubmissionRequest;
use Rominas\Catalog\NomineeSubmission\Requests\LinkNomineeSubmissionRequest;
use Rominas\Catalog\NomineeSubmission\Requests\RejectNomineeSubmissionRequest;
use Rominas\Catalog\NomineeSubmission\Resources\NomineeSubmissionResource;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

use function abort_unless;

/**
 * Admin reconciliation of an edition's free-text nominee submissions (guard `sanctum` + the
 * `nomineeSubmissions` permission). Each submission is resolved once: link it to an existing Catalog
 * entity, create a new one from it, or reject it.
 */
class NomineeSubmissionsController
{
    public function index(Edition $edition, Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = NomineeSubmission::query()->visibleToUser($user)->forEdition($edition)->withCount('rankings');

        if ($request->filled('status')) {
            $query = $query->filterByStatus(NomineeSubmissionStatus::from((string) $request->query('status')));
        }

        if ($request->filled('type')) {
            $query = $query->filterByType(NomineeType::from((string) $request->query('type')));
        }

        return NomineeSubmissionResource::collection(
            $query
                ->search($request->query('search'))
                ->orderBy($request->query('orderBy', 'created_at'), $request->query('orderDir', 'desc'))
                ->paginate($request->query('perPage', NomineeSubmissionQueryBuilder::PER_PAGE)),
        );
    }

    public function show(
        Edition $edition,
        NomineeSubmission $nomineeSubmission,
        Request $request,
        SuggestCatalogMatchesAction $suggest,
    ): NomineeSubmissionResource {
        $this->assertInEdition($edition, $nomineeSubmission);

        $nomineeSubmission->loadCount('rankings')->load('resolvedNominee');

        if ($request->boolean('with_suggestions')) {
            $nomineeSubmission->suggestions = $suggest->execute($nomineeSubmission);
        }

        return new NomineeSubmissionResource($nomineeSubmission);
    }

    public function link(
        Edition $edition,
        NomineeSubmission $nomineeSubmission,
        LinkNomineeSubmissionRequest $request,
        LinkNomineeSubmissionAction $action,
    ): NomineeSubmissionResource {
        $this->assertInEdition($edition, $nomineeSubmission);

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $submission = $action->execute($nomineeSubmission, (int) $validated['nominee_id'], $user, $validated['note'] ?? null);

        return new NomineeSubmissionResource($submission->loadCount('rankings')->load('resolvedNominee'));
    }

    public function create(
        Edition $edition,
        NomineeSubmission $nomineeSubmission,
        CreateNomineeFromSubmissionRequest $request,
        CreateNomineeFromSubmissionAction $action,
    ): NomineeSubmissionResource {
        $this->assertInEdition($edition, $nomineeSubmission);

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $submission = $action->execute($nomineeSubmission, $user, $validated['name'] ?? null, $validated['note'] ?? null);

        return new NomineeSubmissionResource($submission->loadCount('rankings')->load('resolvedNominee'));
    }

    public function reject(
        Edition $edition,
        NomineeSubmission $nomineeSubmission,
        RejectNomineeSubmissionRequest $request,
        RejectNomineeSubmissionAction $action,
    ): NomineeSubmissionResource {
        $this->assertInEdition($edition, $nomineeSubmission);

        /** @var User $user */
        $user = $request->user();

        $submission = $action->execute($nomineeSubmission, $user, $request->validated()['note'] ?? null);

        return new NomineeSubmissionResource($submission->loadCount('rankings'));
    }

    private function assertInEdition(Edition $edition, NomineeSubmission $submission): void
    {
        abort_unless($submission->edition_id === $edition->id, 404);
    }
}
