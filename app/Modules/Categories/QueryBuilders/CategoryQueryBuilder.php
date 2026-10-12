<?php

declare(strict_types=1);

namespace Rominas\Categories\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Shared\Concerns\QueryBuilderSearchableTrait;
use Rominas\Shared\Concerns\QueryBuilderSortableTrait;
use Rominas\Users\Model\User;

/**
 * @extends Builder<Category>
 */
class CategoryQueryBuilder extends Builder
{
    use QueryBuilderSearchableTrait;
    use QueryBuilderSortableTrait;

    public const PER_PAGE = 25;

    public function actionableByUser(User $user, string $action): self
    {
        $ids = $user->getPermissionTargets('categories', $action);

        if ($ids->isNotEmpty()) {
            $this->whereIn('id', $ids->toArray());
        }

        return $this;
    }

    public function visibleToUser(User $user): self
    {
        return $this->actionableByUser($user, 'view');
    }

    public function filterByEditionId(int $editionId): self
    {
        return $this->where('edition_id', '=', $editionId);
    }

    public function ofKind(CategoryKind $kind): self
    {
        return $this->where('kind', '=', $kind->value);
    }

    public function ranked(): self
    {
        return $this->ofKind(CategoryKind::Ranked);
    }

    /**
     * The categories that do not follow the ranked flow: propose-or-vote and appointed.
     */
    public function special(): self
    {
        return $this->where('kind', '!=', CategoryKind::Ranked->value);
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
            'position',
            'created_at',
            'updated_at',
        ];
    }
}
