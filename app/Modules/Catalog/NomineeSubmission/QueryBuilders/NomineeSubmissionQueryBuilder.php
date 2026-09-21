<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Editions\Model\Edition;
use Rominas\Shared\Concerns\QueryBuilderSearchableTrait;
use Rominas\Shared\Concerns\QueryBuilderSortableTrait;
use Rominas\Users\Model\User;

/**
 * @extends Builder<NomineeSubmission>
 */
class NomineeSubmissionQueryBuilder extends Builder
{
    use QueryBuilderSearchableTrait;
    use QueryBuilderSortableTrait;

    public const PER_PAGE = 25;

    public function actionableByUser(User $user, string $action): self
    {
        $ids = $user->getPermissionTargets('nomineeSubmissions', $action);

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

    public function pending(): self
    {
        return $this->where('status', '=', NomineeSubmissionStatus::Pending->value);
    }

    public function filterByStatus(NomineeSubmissionStatus $status): self
    {
        return $this->where('status', '=', $status->value);
    }

    public function filterByType(NomineeType $type): self
    {
        return $this->where('nominee_type', '=', $type->value);
    }

    /**
     * @return string[]
     */
    protected function getSearchableFields(): array
    {
        return [
            'raw_name',
            'normalized_name',
        ];
    }

    /**
     * @return string[]
     */
    protected function getSortableFields(): array
    {
        return [
            'id',
            'raw_name',
            'nominee_type',
            'status',
            'created_at',
            'updated_at',
        ];
    }
}
