<?php

declare(strict_types=1);

namespace Rominas\Taxonomies\Model;

use Database\Factories\TaxonomyFactory;
use Rominas\Taxonomies\Policies\TaxonomyPolicy;
use Rominas\Taxonomies\QueryBuilders\TaxonomyQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTaxonomy
 */
#[Fillable([
    'name',
    'hierarchical',
    'allows_multiple',
])]
#[UsePolicy(TaxonomyPolicy::class)]
class Taxonomy extends Model
{
    /** @use HasFactory<TaxonomyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hierarchical' => 'boolean',
            'allows_multiple' => 'boolean',
        ];
    }

    /**
     * @return TaxonomyQueryBuilder
     */
    public static function query(): TaxonomyQueryBuilder
    {
        /** @var TaxonomyQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): TaxonomyQueryBuilder
    {
        return new TaxonomyQueryBuilder($query);
    }

    /**
     * @return HasMany<TaxonomyTerm, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class);
    }
}
