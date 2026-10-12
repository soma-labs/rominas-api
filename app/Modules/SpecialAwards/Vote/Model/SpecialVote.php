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
 * A member's single vote in a propose-or-vote category. Proposing an entry casts the proposer's vote for
 * it, so the row is also the record that the member has acted in the category: the table is unique on
 * (category, member), which is what keeps a member from both proposing and voting.
 *
 * @mixin IdeHelperSpecialVote
 */
#[Fillable([
    'category_id',
    'member_id',
    'special_entry_id',
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

    /**
     * Whether this vote is the one a member cast by proposing the entry, rather than by choosing it.
     */
    public function isOnOwnProposal(): bool
    {
        return $this->entry->proposed_by_member_id === $this->member_id;
    }
}
