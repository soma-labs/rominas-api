<?php

declare(strict_types=1);

namespace Rominas\Catalog\NomineeSubmission\Model;

use Database\Factories\NomineeSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rominas\Academy\Nomination\Model\NominationRanking;
use Rominas\Catalog\Enums\NomineeType;
use Rominas\Catalog\NomineeSubmission\Enums\NomineeSubmissionStatus;
use Rominas\Catalog\NomineeSubmission\Policies\NomineeSubmissionPolicy;
use Rominas\Catalog\NomineeSubmission\QueryBuilders\NomineeSubmissionQueryBuilder;
use Rominas\Editions\Model\Edition;
use Rominas\Users\Model\User;

/**
 * A free-text nominee typed by an academy member on the ballot, staged for reconciliation. Deduplicated
 * per `(edition, nominee_type, normalized_name)` so every member who types the same name shares one row —
 * an admin resolves the name once. Approving it links (or creates) a canonical Catalog entity
 * (`resolved_nominee_id`), after which the pointing rankings' `nominee_id` is backfilled.
 *
 * @property list<array{id: int, name: string, slug: string, score: float}>|null $suggestions transient,
 *           non-persisted match suggestions attached by the show endpoint (see SuggestCatalogMatchesAction)
 *
 * @mixin IdeHelperNomineeSubmission
 */
#[Fillable([
    'edition_id',
    'nominee_type',
    'raw_name',
    'normalized_name',
    'status',
    'resolved_nominee_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
])]
#[UsePolicy(NomineeSubmissionPolicy::class)]
class NomineeSubmission extends Model
{
    /** @use HasFactory<NomineeSubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominee_type' => NomineeType::class,
            'status' => NomineeSubmissionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public static function query(): NomineeSubmissionQueryBuilder
    {
        /** @var NomineeSubmissionQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): NomineeSubmissionQueryBuilder
    {
        return new NomineeSubmissionQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * The canonical Catalog entity this submission was linked to on approval (null while pending). The
     * morph type is the submission's own `nominee_type` slug, resolved via the morph map.
     *
     * @return MorphTo<Model, $this>
     */
    public function resolvedNominee(): MorphTo
    {
        return $this->morphTo('resolvedNominee', 'nominee_type', 'resolved_nominee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return HasMany<NominationRanking, $this>
     */
    public function rankings(): HasMany
    {
        return $this->hasMany(NominationRanking::class);
    }
}
