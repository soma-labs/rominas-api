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
use LogicException;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Categories\Enums\CategoryKind;
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
    'kind',
    'nominee_type',
    'position',
    'description',
    'placeholder',
    'eligibility_criteria',
])]
#[UsePolicy(CategoryPolicy::class)]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => CategoryKind::Ranked->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
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

    public function isRanked(): bool
    {
        return $this->kind === CategoryKind::Ranked;
    }

    /**
     * The Catalog entity type a ranked category accepts. Only a ranked category has one, so the ranked
     * flow (ballot, shortlist, scoring) calls this after checking {@see isRanked()}.
     *
     * @throws LogicException
     */
    public function rankedNomineeType(): NomineeType
    {
        if ($this->nominee_type === null) {
            throw new LogicException("Category {$this->id} is not ranked and has no nominee type.");
        }

        return $this->nominee_type;
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }
}
