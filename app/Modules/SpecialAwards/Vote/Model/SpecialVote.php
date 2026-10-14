<?php

declare(strict_types=1);

namespace Rominas\SpecialAwards\Vote\Model;

use Database\Factories\SpecialVoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rominas\Academy\Member\Model\Member;
use Rominas\Categories\Model\Category;
use Rominas\SpecialAwards\Entry\Model\SpecialEntry;
use Rominas\SpecialAwards\Vote\QueryBuilders\SpecialVoteQueryBuilder;

/**
 * A member's vote in a propose-or-vote category. A member holds at most two per category: the one their
 * proposal carries (`via_proposal`, cast when they propose and kept on the target if the entry is merged)
 * and the one they chose for an entry another member proposed. The table is unique on
 * (category, member, via_proposal), which is what caps each kind at one.
 *
 * @mixin IdeHelperSpecialVote
 */
#[Fillable([
    'category_id',
    'member_id',
    'special_entry_id',
    'via_proposal',
])]
class SpecialVote extends Model
{
    /** @use HasFactory<SpecialVoteFactory> */
    use HasFactory;

    /**
     * @return SpecialVoteQueryBuilder
     */
    public static function query(): SpecialVoteQueryBuilder
    {
        /** @var SpecialVoteQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'via_proposal' => 'boolean',
        ];
    }

    public function newEloquentBuilder($query): SpecialVoteQueryBuilder
    {
        return new SpecialVoteQueryBuilder($query);
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
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<SpecialEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(SpecialEntry::class, 'special_entry_id');
    }
}
