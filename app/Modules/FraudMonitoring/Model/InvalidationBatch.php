<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Model;

use Database\Factories\InvalidationBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Policies\InvalidationBatchPolicy;
use Rominas\FraudMonitoring\QueryBuilders\InvalidationBatchQueryBuilder;
use Rominas\Users\Model\User;
use Rominas\Voting\Model\Ballot;

/**
 * One vote-cancellation event: a fraud monitor or custodian invalidates a set of an edition's public
 * ballots for a single, mandatory reason. The batch is the audit unit — it records who cancelled and
 * why; each cancelled {@see Ballot} points back at it via `invalidation_batch_id`, and a ballot is valid
 * (counts toward Scoring) iff that FK is null. Invalidation is terminal — there is no reversal.
 *
 * @mixin IdeHelperInvalidationBatch
 */
#[Fillable([
    'edition_id',
    'reason',
    'invalidated_by',
])]
#[UsePolicy(InvalidationBatchPolicy::class)]
class InvalidationBatch extends Model
{
    /** @use HasFactory<InvalidationBatchFactory> */
    use HasFactory;

    /**
     * @return InvalidationBatchQueryBuilder
     */
    public static function query(): InvalidationBatchQueryBuilder
    {
        /** @var InvalidationBatchQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): InvalidationBatchQueryBuilder
    {
        return new InvalidationBatchQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by');
    }

    /**
     * @return HasMany<Ballot, $this>
     */
    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class);
    }
}
