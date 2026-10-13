<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Enums\MemberProposalStatus;
use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Shared\Concerns\QueryBuilderSearchableTrait;
use Rominas\Shared\Concerns\QueryBuilderSortableTrait;
use Rominas\Users\Model\User;

/**
 * @extends Builder<MemberProposal>
 */
class MemberProposalQueryBuilder extends Builder
{
    use QueryBuilderSearchableTrait;
    use QueryBuilderSortableTrait;

    public const PER_PAGE = 25;

    public function actionableByUser(User $user, string $action): self
    {
        $ids = $user->getPermissionTargets('memberProposals', $action);

        if ($ids->isNotEmpty()) {
            $this->whereIn('id', $ids->toArray());
        }

        return $this;
    }

    public function visibleToUser(User $user): self
    {
        return $this->actionableByUser($user, 'view');
    }

    public function forProposer(Member $member): self
    {
        return $this->where('proposed_by_member_id', '=', $member->id);
    }

    public function filterByStatus(MemberProposalStatus $status): self
    {
        return $this->where('status', '=', $status->value);
    }

    /**
     * The approved proposal that created the member with this address. Matched through the member, so
     * it still holds after an admin corrects the member's address.
     */
    public function approvedForMemberEmail(string $email): self
    {
        return $this
            ->filterByStatus(MemberProposalStatus::Approved)
            ->whereRelation('member', 'email', '=', $email);
    }

    /**
     * @return string[]
     */
    protected function getSearchableFields(): array
    {
        return [
            'name',
            'email',
        ];
    }

    /**
     * @return string[]
     */
    protected function getSortableFields(): array
    {
        return [
            'id',
            'name',
            'email',
            'status',
            'created_at',
            'updated_at',
        ];
    }
}
