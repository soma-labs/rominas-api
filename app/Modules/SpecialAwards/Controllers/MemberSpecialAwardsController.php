<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Requests\NomineeSuggestionsRequest;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\SpecialAwards\Actions\GetMemberSpecialAwardsAction;
use Rominas\SpecialAwards\Entry\Actions\ProposeSpecialEntryAction;
use Rominas\SpecialAwards\Entry\Actions\SuggestSpecialEntriesAction;
use Rominas\SpecialAwards\Entry\Actions\WithdrawSpecialEntryAction;
use Rominas\SpecialAwards\Entry\Factories\SpecialEntryDataFactory;
use Rominas\SpecialAwards\Entry\Requests\SaveSpecialEntryRequest;
use Rominas\SpecialAwards\Entry\Resources\MemberSpecialEntryResource;
use Rominas\SpecialAwards\Resources\MemberSpecialAwardsResource;
use Rominas\SpecialAwards\Vote\Actions\VoteForSpecialEntryAction;
use Rominas\SpecialAwards\Vote\Actions\WithdrawSpecialVoteAction;
use Rominas\SpecialAwards\Vote\Requests\VoteForSpecialEntryRequest;

use function abort;

/**
 * Member-facing special awards (guard `member`): in each propose-or-vote category of the active edition
 * a member either proposes one entry or votes for one approved entry. Ownership is implicit — every
 * action operates on the authenticated member's own proposal or vote. Each write answers with the
 * member's whole, refreshed view.
 */
class MemberSpecialAwardsController
{
    public function __construct(
        private readonly GetMemberSpecialAwardsAction $getSpecialAwards,
    ) {}

    public function show(): MemberSpecialAwardsResource
    {
        return $this->currentView($this->member());
    }

    public function propose(
        Category $category,
        SaveSpecialEntryRequest $request,
        ProposeSpecialEntryAction $action,
    ): MemberSpecialAwardsResource {
        $member = $this->member();

        $action->execute($member, $category, SpecialEntryDataFactory::fromRequest($request));

        return $this->currentView($member);
    }

    public function withdrawProposal(Category $category, WithdrawSpecialEntryAction $action): MemberSpecialAwardsResource
    {
        $member = $this->member();

        $action->execute($member, $category);

        return $this->currentView($member);
    }

    public function vote(
        Category $category,
        VoteForSpecialEntryRequest $request,
        VoteForSpecialEntryAction $action,
    ): MemberSpecialAwardsResource {
        $member = $this->member();

        $action->execute($member, $category, $request->entryId());

        return $this->currentView($member);
    }

    public function withdrawVote(Category $category, WithdrawSpecialVoteAction $action): MemberSpecialAwardsResource
    {
        $member = $this->member();

        $action->execute($member, $category);

        return $this->currentView($member);
    }

    /**
     * Approved entries matching what the member has typed in the proposal's title.
     */
    public function suggestions(
        Category $category,
        NomineeSuggestionsRequest $request,
        SuggestSpecialEntriesAction $action,
    ): AnonymousResourceCollection {
        if ($category->kind !== CategoryKind::ProposeOrVote) {
            abort(404);
        }

        return MemberSpecialEntryResource::collection($action->execute($category, $request->searchTerm()));
    }

    private function member(): Member
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return $member;
    }

    private function currentView(Member $member): MemberSpecialAwardsResource
    {
        $edition = Edition::query()->active()->first();

        if ($edition === null) {
            abort(404, __('There is no active edition.'));
        }

        return new MemberSpecialAwardsResource($this->getSpecialAwards->execute($member, $edition));
    }
}
