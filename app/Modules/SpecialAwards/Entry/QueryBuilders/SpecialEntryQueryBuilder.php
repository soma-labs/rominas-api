<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Rominas\Shared\Concerns\QueryBuilderSearchableTrait;
use Rominas\Shared\Concerns\QueryBuilderSortableTrait;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\Users\Model\User;

/**
 * @extends Builder<SpecialEntry>
 */
class SpecialEntryQueryBuilder extends Builder
{
    use QueryBuilderSearchableTrait;
    use QueryBuilderSortableTrait;

    public const PER_PAGE = 25;

    public function actionableByUser(User $user, string $action): self
    {
        $ids = $user->getPermissionTargets('specialAwards', $action);

        if ($ids->isNotEmpty()) {
            $this->whereIn('id', $ids->toArray());
        }

        return $this;
    }

    public function visibleToUser(User $user): self
    {
        return $this->actionableByUser($user, 'view');
    }

    public function forEdition(Edition $edition): self
    {
        return $this->whereHas('category', fn(Builder $query) => $query->where('edition_id', '=', $edition->id));
    }

    public function forCategoryKind(CategoryKind $kind): self
    {
        return $this->whereHas('category', fn(Builder $query) => $query->where('kind', '=', $kind->value));
    }

    public function forCategory(Category $category): self
    {
        return $this->where('category_id', '=', $category->id);
    }

    public function forProposer(Member $member): self
    {
        return $this->where('proposed_by_member_id', '=', $member->id);
    }

    public function filterByStatus(SpecialEntryStatus $status): self
    {
        return $this->where('status', '=', $status->value);
    }

    public function pending(): self
    {
        return $this->filterByStatus(SpecialEntryStatus::Pending);
    }

    public function approved(): self
    {
        return $this->filterByStatus(SpecialEntryStatus::Approved);
    }

    /**
     * @return string[]
     */
    protected function getSearchableFields(): array
    {
        return [
            'title',
        ];
    }

    /**
     * @return string[]
     */
    protected function getSortableFields(): array
    {
        return [
            'id',
            'title',
            'status',
            'created_at',
            'updated_at',
        ];
    }
}
