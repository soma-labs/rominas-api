<?php

declare(strict_types=1);

namespace Rominas\Catalog\Venue\Model;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rominas\Catalog\Venue\Policies\VenuePolicy;
use Rominas\Catalog\Venue\QueryBuilders\VenueQueryBuilder;

/**
 * @mixin IdeHelperVenue
 */
#[Fillable([
    'name',
    'slug',
    'description',
])]
#[UsePolicy(VenuePolicy::class)]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(static function (Venue $venue): void {
            if (empty($venue->slug)) {
                $venue->slug = Str::slug($venue->name);
            }
        });
    }

    /**
     * @return VenueQueryBuilder
     */
    public static function query(): VenueQueryBuilder
    {
        /** @var VenueQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): VenueQueryBuilder
    {
        return new VenueQueryBuilder($query);
    }
}
