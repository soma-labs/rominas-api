<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Model\Nomination;
use Rominas\Editions\Model\Edition;
use Rominas\Shared\Concerns\QueryBuilderSearchableTrait;
use Rominas\Shared\Concerns\QueryBuilderSortableTrait;
use Rominas\Users\Model\User;

/**
 * @extends Builder<Member>
 */
class MemberQueryBuilder extends Builder
{
    use QueryBuilderSearchableTrait;
    use QueryBuilderSortableTrait;

    public const PER_PAGE = 25;

    public function actionableByUser(User $user, string $action): self
    {
        $ids = $user->getPermissionTargets('members', $action);

        if ($ids->isNotEmpty()) {
            $this->whereIn('id', $ids->toArray());
        }

        return $this;
    }

    public function visibleToUser(User $user): self
    {
        return $this->actionableByUser($user, 'view');
    }

    public function filterByStatus(MemberStatus $status): self
    {
        return $this->where('status', '=', $status->value);
    }

    /**
     * Members who can receive academy e-mails: invited or already active (not suspended, and not
     * still waiting for their invitation).
     */
    public function invitedOrActive(): self
    {
        return $this->whereIn('status', [MemberStatus::Invited->value, MemberStatus::Active->value]);
    }

    public function withoutSubmittedNominationFor(Edition $edition): self
    {
        return $this->whereNotIn(
            'id',
            Nomination::query()->forEdition($edition)->submitted()->select('member_id'),
        );
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
            'created_at',
            'updated_at',
        ];
    }
}
