<?php

declare(strict_types=1);

namespace Rominas\Voting\Model;

use Database\Factories\BallotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Model\InvalidationBatch;
use Rominas\Voting\Enums\BallotStatus;
use Rominas\Voting\QueryBuilders\BallotQueryBuilder;

/**
 * A public voter's single ballot for one edition — the accountless identity, the one-time link, and its
 * lifecycle. There is no account or guard: possession of the (hashed) token is the authorization. The
 * voter is pseudonymized as `email_hash` (unique per edition → one link ever), the link as `token_hash`
 * (unique, single-use). No plaintext personal data is stored. Cast votes live in child ballot_rankings.
 *
 * A ballot is valid iff `invalidation_batch_id` is null; a non-null FK means it was cancelled as part of
 * an {@see InvalidationBatch} (FraudMonitoring) and no longer counts toward Scoring.
 *
 * @mixin IdeHelperBallot
 */
#[Fillable([
    'edition_id',
    'email_hash',
    'token_hash',
    'status',
    'expires_at',
    'submitted_at',
    'ip_hash',
])]
class Ballot extends Model
{
    /** @use HasFactory<BallotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BallotStatus::class,
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BallotQueryBuilder
     */
    public static function query(): BallotQueryBuilder
    {
        /** @var BallotQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): BallotQueryBuilder
    {
        return new BallotQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return HasMany<BallotRanking, $this>
     */
    public function rankings(): HasMany
    {
        return $this->hasMany(BallotRanking::class);
    }

    /**
     * @return BelongsTo<InvalidationBatch, $this>
     */
    public function invalidationBatch(): BelongsTo
    {
        return $this->belongsTo(InvalidationBatch::class);
    }
}
