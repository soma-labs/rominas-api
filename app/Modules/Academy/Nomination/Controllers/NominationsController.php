<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Controllers;

use Illuminate\Support\Facades\Auth;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Actions\SaveCategoryRankingAction;
use Rominas\Academy\Nomination\Actions\SubmitNominationAction;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\Factories\CategoryRankingDataFactory;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Academy\Nomination\Requests\SaveCategoryRankingRequest;
use Rominas\Academy\Nomination\Resources\NominationResource;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;

use function abort;

/**
 * Member-facing ranked nominations (guard `member`). Ownership is implicit — every action operates on
 * the authenticated member's own ballot for the active edition.
 */
class NominationsController
{
    public function show(): NominationResource
    {
        return $this->currentBallot($this->member());
    }

    public function saveCategory(
        Category $category,
        SaveCategoryRankingRequest $request,
        SaveCategoryRankingAction $action,
    ): NominationResource {
        $member = $this->member();

        $action->execute($member, $category, CategoryRankingDataFactory::fromRequest($request, $category));

        return $this->currentBallot($member);
    }

    public function submit(SubmitNominationAction $action): NominationResource
    {
        $member = $this->member();

        $action->execute($member);

        return $this->currentBallot($member);
    }

    private function member(): Member
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return $member;
    }

    /**
     * Build the member's whole ballot for the active edition — the persisted draft/submitted row, or
     * a synthesized empty draft when none exists yet (read-only; not persisted).
     */
    private function currentBallot(Member $member): NominationResource
    {
        $edition = Edition::query()->active()->with('categories')->first();

        if ($edition === null) {
            abort(404, 'There is no active edition.');
        }

        $nomination = Nomination::query()
            ->forMember($member)
            ->forEdition($edition)
            ->with(['rankings.nominee', 'rankings.nomineeSubmission'])
            ->first();

        if ($nomination === null) {
            $nomination = new Nomination([
                'edition_id' => $edition->id,
                'status' => NominationStatus::Draft,
            ]);
            $nomination->setRelation('rankings', collect());
        }

        $nomination->setRelation('edition', $edition);

        return new NominationResource($nomination);
    }
}
