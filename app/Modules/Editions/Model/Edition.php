<?php

declare(strict_types=1);

namespace Rominas\Editions\Model;

use Database\Factories\EditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Policies\EditionPolicy;
use Rominas\Editions\QueryBuilders\EditionQueryBuilder;
use Rominas\Results\Model\ResultSnapshot;

/**
 * @mixin IdeHelperEdition
 */
#[Fillable([
    'name',
    'slug',
    'starts_at',
    'nominations_start_at',
    'nominations_end_at',
    'voting_start_at',
    'voting_end_at',
    'ends_at',
    'status',
    'academy_vote_weight',
    'public_vote_weight',
])]
#[UsePolicy(EditionPolicy::class)]
class Edition extends Model
{
    /** @use HasFactory<EditionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'nominations_start_at' => 'datetime',
            'nominations_end_at' => 'datetime',
            'voting_start_at' => 'datetime',
            'voting_end_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EditionStatus::class,
            'academy_vote_weight' => 'integer',
            'public_vote_weight' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (Edition $edition): void {
            if (empty($edition->slug)) {
                $edition->slug = Str::slug($edition->name);
            }
        });
    }

    /**
     * @return EditionQueryBuilder
     */
    public static function query(): EditionQueryBuilder
    {
        /** @var EditionQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): EditionQueryBuilder
    {
        return new EditionQueryBuilder($query);
    }

    /**
     * The frozen results snapshot, present once the edition's results are published.
     *
     * @return HasOne<ResultSnapshot, $this>
     */
    public function resultSnapshot(): HasOne
    {
        return $this->hasOne(ResultSnapshot::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
