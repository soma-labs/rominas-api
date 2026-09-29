<?php

declare(strict_types=1);

namespace Rominas\Academy\Nomination\Model;

use Database\Factories\NominationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rominas\Academy\Member\Model\Member;
use Rominas\Academy\Nomination\Enums\NominationStatus;
use Rominas\Academy\Nomination\QueryBuilders\NominationQueryBuilder;
use Rominas\Editions\Model\Edition;

/**
 * A member's nomination ballot for one edition (one row per member+edition). Holds the draft→submitted
 * state; the ranked picks live in child NominationRanking rows.
 *
 * @mixin IdeHelperNomination
 */
#[Fillable([
    'member_id',
    'edition_id',
    'status',
    'submitted_at',
])]
class Nomination extends Model
{
    /** @use HasFactory<NominationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NominationStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return NominationQueryBuilder
     */
    public static function query(): NominationQueryBuilder
    {
        /** @var NominationQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): NominationQueryBuilder
    {
        return new NominationQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return HasMany<NominationRanking, $this>
     */
    public function rankings(): HasMany
    {
        return $this->hasMany(NominationRanking::class);
    }
}
