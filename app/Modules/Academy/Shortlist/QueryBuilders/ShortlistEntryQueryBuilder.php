<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Academy\Shortlist\Model\ShortlistEntry;
use Rominas\Categories\Model\Category;
use Rominas\Categories\QueryBuilders\CategoryQueryBuilder;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

/**
 * @extends Builder<ShortlistEntry>
 */
class ShortlistEntryQueryBuilder extends Builder
{
    public const PER_PAGE = 25;

    public function actionableByUser(User $user, string $action): self
    {
        $ids = $user->getPermissionTargets('shortlists', $action);

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
        return $this->where('edition_id', '=', $edition->id);
    }

    public function forCategory(Category $category): self
    {
        return $this->where('category_id', '=', $category->id);
    }

    /**
     * Entries of categories shown on the public voting ballot.
     */
    public function inPubliclyVisibleCategories(): self
    {
        return $this->whereHas('category', fn(CategoryQueryBuilder $query) => $query->publiclyVisible());
    }
}
