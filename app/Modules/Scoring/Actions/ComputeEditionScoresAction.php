<?php

declare(strict_types=1);

namespace Rominas\Scoring\Actions;

use Illuminate\Support\Facades\Cache;
use Rominas\Categories\Model\Category;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
use Rominas\Scoring\DataTransferObjects\EditionScore;
use Rominas\Scoring\Enums\ScoringAlgorithmType;
use Rominas\Scoring\Support\ScoringAlgorithm;

/**
 * Computes an entire edition's results — every category, in order — on demand.
 *
 * The inputs are frozen the moment public voting closes (no new nominations or ballots in the scorable
 * states), so the result is cached per edition and status; a status change (e.g. results being published) yields
 * a new key (as does switching algorithm), and passing `fresh: true` forces a recompute. Nothing is persisted — the (future) Results
 * module owns the custodian-gated view/export and the snapshot frozen when results are published.
 */
class ComputeEditionScoresAction
{
    public function __construct(
        private readonly ComputeCategoryScoresAction $computeCategory,
        private readonly ScoringAlgorithm $algorithm,
    ) {}

    public function execute(Edition $edition, bool $fresh = false): EditionScore
    {
        $this->computeCategory->assertScorable($edition);

        $key = self::cacheKey($edition, $this->algorithm->type(), $edition->status);

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addHours(6), function () use ($edition): EditionScore {
            $categories = $edition->categories()
                ->get()
                ->sortBy('position')
                ->values();

            $scores = [];

            foreach ($categories as $category) {
                /** @var Category $category */
                $scores[] = $this->computeCategory->execute($edition, $category);
            }

            return new EditionScore($edition->id, $scores, $this->algorithm->type());
        });
    }

    /**
     * The cache key for one edition's scores under a given status and algorithm.
     */
    public static function cacheKey(Edition $edition, ScoringAlgorithmType $algorithm, EditionStatus $status): string
    {
        return "scoring:edition:{$edition->id}:{$status->value}:{$algorithm->value}";
    }
}
