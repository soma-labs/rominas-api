<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Entry\Model;

use Database\Factories\SpecialEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rominas\Academy\Member\Model\Member;
use Rominas\Catalog\NomineeSubmission\Support\NomineeNameNormalizer;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Entry\Enums\SpecialEntryStatus;
use Rominas\SpecialAwards\Entry\Policies\SpecialEntryPolicy;
use Rominas\SpecialAwards\Entry\QueryBuilders\SpecialEntryQueryBuilder;
use Rominas\SpecialAwards\Vote\Model\SpecialVote;
use Rominas\Users\Model\User;

/**
 * A free-text entry in a special category (one that is not ranked). In a propose-or-vote category a
 * member proposes it and, once an admin approves it, other members vote for it. In an appointed category
 * an admin enters it as the result (`proposed_by_member_id` is null).
 *
 * @property list<array{id: int, title: string, score: float}>|null $similar
 *           non-persisted similar approved entries attached by the show endpoint
 * @mixin IdeHelperSpecialEntry
 */
#[Fillable([
    'category_id',
    'title',
    'status',
    'proposed_by_member_id',
    'merged_into_entry_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
])]
#[UsePolicy(SpecialEntryPolicy::class)]
class SpecialEntry extends Model
{
    /** @use HasFactory<SpecialEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SpecialEntryStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (SpecialEntry $entry): void {
            $entry->normalized_title = NomineeNameNormalizer::normalize($entry->title);
        });
    }

    /**
     * @return SpecialEntryQueryBuilder
     */
    public static function query(): SpecialEntryQueryBuilder
    {
        /** @var SpecialEntryQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): SpecialEntryQueryBuilder
    {
        return new SpecialEntryQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'proposed_by_member_id');
    }

    /**
     * The entry this duplicate was folded into.
     *
     * @return BelongsTo<SpecialEntry, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return HasMany<SpecialVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(SpecialVote::class);
    }
}
