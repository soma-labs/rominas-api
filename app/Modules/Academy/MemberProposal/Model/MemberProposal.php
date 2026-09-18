<?php

declare(strict_types=1);

namespace Rominas\Academy\MemberProposal\Model;

use Database\Factories\MemberProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\MemberProposal\Enums\MemberProposalStatus;
use Rominas\Academy\MemberProposal\Policies\MemberProposalPolicy;
use Rominas\Academy\MemberProposal\QueryBuilders\MemberProposalQueryBuilder;
use Rominas\Users\Model\User;

/**
 * A standing proposal, made by an academy member, to add a future academy member. Admins review it;
 * approving it creates an invited Member (`member_id`). Not edition-scoped — a running pool.
 *
 * @mixin IdeHelperMemberProposal
 */
#[Fillable([
    'proposed_by_member_id',
    'name',
    'email',
    'position',
    'company',
    'phone',
    'reason',
    'status',
    'member_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
])]
#[UsePolicy(MemberProposalPolicy::class)]
class MemberProposal extends Model
{
    /** @use HasFactory<MemberProposalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MemberProposalStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public static function query(): MemberProposalQueryBuilder
    {
        /** @var MemberProposalQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): MemberProposalQueryBuilder
    {
        return new MemberProposalQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'proposed_by_member_id');
    }

    /**
     * The invited Member created when the proposal was approved.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
