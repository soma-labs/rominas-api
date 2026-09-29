<?php

declare(strict_types=1);

namespace Rominas\Catalog\Band\Model;

use Database\Factories\BandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rominas\Catalog\Band\Policies\BandPolicy;
use Rominas\Catalog\Band\QueryBuilders\BandQueryBuilder;

/**
 * @mixin IdeHelperBand
 */
#[Fillable([
    'name',
    'slug',
    'description',
])]
#[UsePolicy(BandPolicy::class)]
class Band extends Model
{
    /** @use HasFactory<BandFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(static function (Band $band): void {
            if (empty($band->slug)) {
                $band->slug = Str::slug($band->name);
            }
        });
    }

    /**
     * @return BandQueryBuilder
     */
    public static function query(): BandQueryBuilder
    {
        /** @var BandQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): BandQueryBuilder
    {
        return new BandQueryBuilder($query);
    }
}
