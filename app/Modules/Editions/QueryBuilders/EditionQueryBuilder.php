<?php

declare(strict_types=1);

namespace Rominas\Editions\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Shared\Concerns\QueryBuilderSearchableTrait;
use Rominas\Shared\Concerns\QueryBuilderSortableTrait;
use Rominas\Users\Model\User;

/**
 * @extends Builder<Edition>
 */
class EditionQueryBuilder extends Builder
{
    use QueryBuilderSearchableTrait;
    use QueryBuilderSortableTrait;

    public const PER_PAGE = 25;

    public function actionableByUser(User $user, string $action): self
    {
        $ids = $user->getPermissionTargets('editions', $action);

        if ($ids->isNotEmpty()) {
            $this->whereIn('id', $ids->toArray());
        }

        return $this;
    }

    public function visibleToUser(User $user): self
    {
        return $this->actionableByUser($user, 'view');
    }

    public function filterByStatus(EditionStatus $status): self
    {
        return $this->where('status', '=', $status->value);
    }

    /**
     * Editions that are not archived. At most one may exist at a time.
     */
    public function active(): self
    {
        return $this->where('status', '!=', EditionStatus::Archived->value);
    }

    /**
     * Editions whose results can be read: public voting has closed (the Scoring gate), or a frozen
     * snapshot already exists (which also covers archived editions).
     */
    public function withResultsAvailable(): self
    {
        return $this->where(function (Builder $query): void {
            $query->whereIn('status', [
                EditionStatus::VotingClosed->value,
                EditionStatus::CommitteeReview->value,
                EditionStatus::ResultsPublished->value,
            ])->orHas('resultSnapshot');
        });
    }

    /**
     * @return string[]
     */
    protected function getSearchableFields(): array
    {
        return [
            'name',
            'slug',
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
            'starts_at',
            'ends_at',
            'created_at',
            'updated_at',
        ];
    }
}
