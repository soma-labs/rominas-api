<?php

declare(strict_types=1);

namespace Rominas\Categories\Model;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Policies\CategoryPolicy;
use Rominas\Categories\QueryBuilders\CategoryQueryBuilder;
use Rominas\Editions\Model\Edition;

/**
 * @mixin IdeHelperCategory
 */
#[Fillable([
    'edition_id',
    'name',
    'slug',
    'nominee_type',
    'position',
    'description',
])]
#[UsePolicy(CategoryPolicy::class)]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominee_type' => NomineeType::class,
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (Category $category): void {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * @return CategoryQueryBuilder
     */
    public static function query(): CategoryQueryBuilder
    {
        /** @var CategoryQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): CategoryQueryBuilder
    {
        return new CategoryQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }
}
