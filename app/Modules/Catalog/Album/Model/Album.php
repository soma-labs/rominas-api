<?php

declare(strict_types=1);

namespace Rominas\Catalog\Album\Model;

use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rominas\Catalog\Album\Policies\AlbumPolicy;
use Rominas\Catalog\Album\QueryBuilders\AlbumQueryBuilder;

/**
 * @mixin IdeHelperAlbum
 */
#[Fillable([
    'name',
    'slug',
    'description',
])]
#[UsePolicy(AlbumPolicy::class)]
class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(static function (Album $album): void {
            if (empty($album->slug)) {
                $album->slug = Str::slug($album->name);
            }
        });
    }

    /**
     * @return AlbumQueryBuilder
     */
    public static function query(): AlbumQueryBuilder
    {
        /** @var AlbumQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): AlbumQueryBuilder
    {
        return new AlbumQueryBuilder($query);
    }
}
