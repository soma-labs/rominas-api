<?php

declare(strict_types=1);

namespace Rominas\Results\Model;

use Database\Factories\ResultEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;

/**
 * One nominee's frozen standing within a category of an edition whose results are published: the raw summed academy and
 * public points, each side's normalized share, the weighted `final_score` and the resulting `position`
 * (1 = winner). Mirrors Scoring's NomineeScore DTO; the nominee is polymorphic via the stable
 * NomineeType slug (morph map), not a FQN.
 *
 * @mixin IdeHelperResultEntry
 */
#[Fillable([
    'result_snapshot_id',
    'category_id',
    'nominee_type',
    'nominee_id',
    'academy_points',
    'public_points',
    'academy_share',
    'public_share',
    'final_score',
    'position',
])]
class ResultEntry extends Model
{
    /** @use HasFactory<ResultEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominee_type' => NomineeType::class,
            'academy_points' => 'integer',
            'public_points' => 'integer',
            'academy_share' => 'float',
            'public_share' => 'float',
            'final_score' => 'float',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ResultSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ResultSnapshot::class, 'result_snapshot_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function nominee(): MorphTo
    {
        return $this->morphTo();
    }
}
