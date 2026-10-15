<?php

declare(strict_types=1);

namespace Rominas\Menu\Actions;

use Rominas\Academy\MemberProposal\Enums\MemberProposalStatus;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

class GetMenuCountsForUserAction
{
    /**
     * The sidebar badge counts — work waiting for an admin — keyed by menu item id. A count is left out
     * when the user may not see the list it points to.
     *
     * - `sidenav.editions`: the active edition's nominee submissions still to reconcile.
     * - `sidenav.member-proposals`: member proposals awaiting review.
     *
     * @return array<string, int>
     */
    public function execute(User $user): array
    {
        $counts = [];

        if ($user->can('viewAny', NomineeSubmission::class)) {
            $counts['sidenav.editions'] = $this->countPendingNomineeSubmissions($user);
        }

        if ($user->can('viewAny', MemberProposal::class)) {
            $counts['sidenav.member-proposals'] = MemberProposal::query()
                ->visibleToUser($user)
                ->filterByStatus(MemberProposalStatus::Pending)
                ->count();
        }

        return $counts;
    }

    private function countPendingNomineeSubmissions(User $user): int
    {
        $activeEdition = Edition::query()->active()->first();

        if ($activeEdition === null) {
            return 0;
        }

        return NomineeSubmission::query()
            ->visibleToUser($user)
            ->forEdition($activeEdition)
            ->pending()
            ->count();
    }
}
