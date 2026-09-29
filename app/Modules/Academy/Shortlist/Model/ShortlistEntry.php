<?php

declare(strict_types=1);

namespace Rominas\Academy\Shortlist\Model;

use Database\Factories\ShortlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rominas\Academy\Shortlist\Policies\ShortlistEntryPolicy;
use Rominas\Academy\Shortlist\QueryBuilders\ShortlistEntryQueryBuilder;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Model\Edition;

/**
 * One finalist on a category's public-voting shortlist: a Catalog entity (`nominee`) that advanced from
 * the academy round at `position` (1 = top) with `points` summed academy points. Frozen snapshot generated
 * on demand by admins once nominations close — the immutable candidate list the public ballot then ranks.
 *
 * The nominee is polymorphic — `nominee_type` stores the stable NomineeType slug (morph map), not a FQN.
 *
 * @mixin IdeHelperShortlistEntry
 */
#[Fillable([
    'edition_id',
    'category_id',
    'nominee_type',
    'nominee_id',
    'points',
    'position',
])]
#[UsePolicy(ShortlistEntryPolicy::class)]
class ShortlistEntry extends Model
{
    /** @use HasFactory<ShortlistEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominee_type' => NomineeType::class,
            'points' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return ShortlistEntryQueryBuilder
     */
    public static function query(): ShortlistEntryQueryBuilder
    {
        /** @var ShortlistEntryQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): ShortlistEntryQueryBuilder
    {
        return new ShortlistEntryQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
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
