<?php

declare(strict_types=1);

namespace Rominas\Results\Model;

use Database\Factories\ResultSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rominas\Editions\Model\Edition;
use Rominas\Results\Policies\ResultPolicy;
use Rominas\Results\QueryBuilders\ResultSnapshotQueryBuilder;
use Rominas\Scoring\Enums\ScoringAlgorithmType;

/**
 * An edition's frozen, custodian-gated results snapshot: the immutable set of {@see ResultEntry} rows
 * computed by Scoring and persisted the moment the edition transitions to `results_published`. The
 * captured vote weights make the snapshot self-describing regardless of later Edition edits.
 *
 * @mixin IdeHelperResultSnapshot
 */
#[Fillable([
    'edition_id',
    'academy_vote_weight',
    'public_vote_weight',
    'algorithm',
    'published_at',
])]
#[UsePolicy(ResultPolicy::class)]
class ResultSnapshot extends Model
{
    /** @use HasFactory<ResultSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'academy_vote_weight' => 'integer',
            'public_vote_weight' => 'integer',
            'algorithm' => ScoringAlgorithmType::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return ResultSnapshotQueryBuilder
     */
    public static function query(): ResultSnapshotQueryBuilder
    {
        /** @var ResultSnapshotQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): ResultSnapshotQueryBuilder
    {
        return new ResultSnapshotQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return HasMany<ResultEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ResultEntry::class);
    }
}
