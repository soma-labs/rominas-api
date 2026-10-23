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
use Rominas\Academy\Nomination\Enums\NominationStatus;

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

    public function filterByStatuses(MemberStatus ...$statuses): self
    {
        return $this->whereIn('status', array_map(fn(MemberStatus $status): string => $status->value, $statuses));
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

    public function withMemberProgress(?Edition $edition): self
    {
        $this->select('members.*');

        $this->selectRaw('
        EXISTS (
            SELECT 1
            FROM member_proposals
            WHERE member_proposals.proposed_by_member_id = members.id
        ) AS has_submitted_member_proposals
    ');

        if ($edition === null) {
            return $this->selectRaw('
            NULL AS has_started_nominations,
            NULL AS has_submitted_nominations
        ');
        }

        $this->selectRaw('
        EXISTS (
            SELECT 1
            FROM nominations
            INNER JOIN nomination_rankings
                ON nomination_rankings.nomination_id = nominations.id
            WHERE nominations.member_id = members.id
              AND nominations.edition_id = ?
        ) AS has_started_nominations
    ', [$edition->id]);

        return $this->selectRaw('
        EXISTS (
            SELECT 1
            FROM nominations
            WHERE nominations.member_id = members.id
              AND nominations.edition_id = ?
              AND nominations.status = ?
        ) AS has_submitted_nominations
    ', [$edition->id, NominationStatus::Submitted->value]);
    }

    public function filterByStartedNominations(
        bool $started,
        ?Edition $edition,
    ): self {
        if ($edition === null) {
            return $this;
        }

        $operator = $started ? 'EXISTS' : 'NOT EXISTS';

        return $this->whereRaw("
        {$operator} (
            SELECT 1
            FROM nominations
            INNER JOIN nomination_rankings
                ON nomination_rankings.nomination_id = nominations.id
            WHERE nominations.member_id = members.id
              AND nominations.edition_id = ?
        )
    ", [$edition->id]);
    }

    public function filterBySubmittedNominations(
        bool $submitted,
        ?Edition $edition,
    ): self {
        if ($edition === null) {
            return $this;
        }

        $operator = $submitted ? 'EXISTS' : 'NOT EXISTS';

        return $this->whereRaw("
        {$operator} (
            SELECT 1
            FROM nominations
            WHERE nominations.member_id = members.id
              AND nominations.edition_id = ?
              AND nominations.status = ?
        )
    ", [$edition->id, NominationStatus::Submitted->value]);
    }

    public function filterByFoundingMember(bool $isFoundingMember): self
    {
        return $this->where('is_founding_member', '=', $isFoundingMember);
    }

    public function filterBySubmittedMemberProposals(bool $submitted): self
    {
        $operator = $submitted ? 'EXISTS' : 'NOT EXISTS';

        return $this->whereRaw("
        {$operator} (
            SELECT 1
            FROM member_proposals
            WHERE member_proposals.proposed_by_member_id = members.id
        )
    ");
    }
}
