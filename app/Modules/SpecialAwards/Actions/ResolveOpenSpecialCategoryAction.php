<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Actions;

use Illuminate\Validation\ValidationException;
use Rominas\Academy\Nomination\Actions\ResolveOpenNominationEditionAction;
use Rominas\Categories\Enums\CategoryKind;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The gate every member write in a special category passes: the category must be a propose-or-vote one
 * (to a member, any other category does not exist here) of the edition whose nomination window is open
 * now. Members propose and vote in the same window in which they nominate.
 */
class ResolveOpenSpecialCategoryAction
{
    public function __construct(
        private readonly ResolveOpenNominationEditionAction $resolveOpenEdition,
    ) {}

    /**
     * @throws NotFoundHttpException
     * @throws ValidationException
     */
    public function execute(Category $category): Edition
    {
        if ($category->kind !== CategoryKind::ProposeOrVote) {
            throw new NotFoundHttpException();
        }

        $edition = $this->resolveOpenEdition->execute();

        if ($category->edition_id !== $edition->id) {
            throw ValidationException::withMessages([
                'category' => __('This category does not belong to the open edition.'),
            ]);
        }

        return $edition;
    }
}
