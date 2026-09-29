<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Model;

use Database\Factories\FraudAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Rominas\Editions\Model\Edition;
use Rominas\FraudMonitoring\Enums\FraudAlertSeverity;
use Rominas\FraudMonitoring\Enums\FraudAlertStatus;
use Rominas\FraudMonitoring\Enums\FraudAlertType;
use Rominas\FraudMonitoring\Policies\FraudAlertPolicy;
use Rominas\FraudMonitoring\QueryBuilders\FraudAlertQueryBuilder;
use Rominas\Voting\Model\Ballot;

/**
 * A recorded finding from the scheduled fraud detectors: a cluster of an edition's submitted public
 * ballots that looks suspicious (a shared IP, a submission burst, or an identical ranking pattern). The
 * `signature` dedupes findings across runs; the implicated ballots are linked via the `fraud_alert_ballot`
 * pivot; a fraud monitor / custodian triages via `status`. Context holds hashes only — no plaintext PII.
 *
 * @mixin IdeHelperFraudAlert
 */
#[Fillable([
    'edition_id',
    'type',
    'signature',
    'severity',
    'status',
    'context',
    'ballot_count',
    'first_detected_at',
    'last_detected_at',
])]
#[UsePolicy(FraudAlertPolicy::class)]
class FraudAlert extends Model
{
    /** @use HasFactory<FraudAlertFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FraudAlertType::class,
            'severity' => FraudAlertSeverity::class,
            'status' => FraudAlertStatus::class,
            'context' => 'array',
            'ballot_count' => 'integer',
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
        ];
    }

    /**
     * @return FraudAlertQueryBuilder
     */
    public static function query(): FraudAlertQueryBuilder
    {
        /** @var FraudAlertQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): FraudAlertQueryBuilder
    {
        return new FraudAlertQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return BelongsToMany<Ballot, $this>
     */
    public function ballots(): BelongsToMany
    {
        return $this->belongsToMany(Ballot::class, 'fraud_alert_ballot');
    }
}
