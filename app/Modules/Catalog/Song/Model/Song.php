<?php

declare(strict_types=1);

namespace Rominas\Catalog\Song\Model;

use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rominas\Catalog\Song\Policies\SongPolicy;
use Rominas\Catalog\Song\QueryBuilders\SongQueryBuilder;

/**
 * @mixin IdeHelperSong
 */
#[Fillable([
    'name',
    'slug',
    'description',
])]
#[UsePolicy(SongPolicy::class)]
class Song extends Model
{
    /** @use HasFactory<SongFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(static function (Song $song): void {
            if (empty($song->slug)) {
                $song->slug = Str::slug($song->name);
            }
        });
    }

    /**
     * @return SongQueryBuilder
     */
    public static function query(): SongQueryBuilder
    {
        /** @var SongQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): SongQueryBuilder
    {
        return new SongQueryBuilder($query);
    }
}
