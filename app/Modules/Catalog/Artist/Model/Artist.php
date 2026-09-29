<?php

declare(strict_types=1);

namespace Rominas\Catalog\Artist\Model;

use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rominas\Catalog\Artist\Policies\ArtistPolicy;
use Rominas\Catalog\Artist\QueryBuilders\ArtistQueryBuilder;

/**
 * @mixin IdeHelperArtist
 */
#[Fillable([
    'name',
    'slug',
    'description',
])]
#[UsePolicy(ArtistPolicy::class)]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(static function (Artist $artist): void {
            if (empty($artist->slug)) {
                $artist->slug = Str::slug($artist->name);
            }
        });
    }

    /**
     * @return ArtistQueryBuilder
     */
    public static function query(): ArtistQueryBuilder
    {
        /** @var ArtistQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): ArtistQueryBuilder
    {
        return new ArtistQueryBuilder($query);
    }
}
