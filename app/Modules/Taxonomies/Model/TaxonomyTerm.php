<?php

declare(strict_types=1);

namespace Rominas\Taxonomies\Model;

use Database\Factories\TaxonomyTermFactory;
use Rominas\Taxonomies\Policies\TaxonomyTermPolicy;
use Rominas\Taxonomies\QueryBuilders\TaxonomyTermQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperTaxonomyTerm
 */
#[Fillable([
    'name',
    'slug',
    'meta',
])]
#[UsePolicy(TaxonomyTermPolicy::class)]
class TaxonomyTerm extends Model
{
    /** @use HasFactory<TaxonomyTermFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $with = ['taxonomy'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => TaxonomyTermMetaCast::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (TaxonomyTerm $taxonomyTerm): void {
            if (empty($taxonomyTerm->slug)) {
                $taxonomyTerm->slug = Str::slug($taxonomyTerm->name);
            }
        });
    }

    /**
     * @return TaxonomyTermQueryBuilder
     */
    public static function query(): TaxonomyTermQueryBuilder
    {
        /** @var TaxonomyTermQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): TaxonomyTermQueryBuilder
    {
        return new TaxonomyTermQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Taxonomy, $this>
     */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    /**
     * @return BelongsTo<TaxonomyTerm, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<TaxonomyTerm, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->excludeHidden();
    }
}
