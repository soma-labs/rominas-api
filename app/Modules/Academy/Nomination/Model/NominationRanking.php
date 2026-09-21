<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Model;

use Database\Factories\NominationRankingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Categories\Model\Category;

/**
 * One ranked pick within a ballot: the member placed a Catalog entity (`nominee`) at position `rank`
 * (1 = top) in a category. The nominee is polymorphic — `nominee_type` stores the stable NomineeType
 * slug (resolved via the morph map registered in AppServiceProvider), never a class FQN.
 *
 * @mixin IdeHelperNominationRanking
 */
#[Fillable([
    'nomination_id',
    'category_id',
    'rank',
    'nominee_submission_id',
    'nominee_type',
    'nominee_id',
])]
class NominationRanking extends Model
{
    /** @use HasFactory<NominationRankingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'nominee_type' => NomineeType::class,
        ];
    }

    /**
     * @return BelongsTo<Nomination, $this>
     */
    public function nomination(): BelongsTo
    {
        return $this->belongsTo(Nomination::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The staged free-text submission this pick was typed as; reconciling it backfills `nominee_id`.
     *
     * @return BelongsTo<NomineeSubmission, $this>
     */
    public function nomineeSubmission(): BelongsTo
    {
        return $this->belongsTo(NomineeSubmission::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function nominee(): MorphTo
    {
        return $this->morphTo();
    }
}
