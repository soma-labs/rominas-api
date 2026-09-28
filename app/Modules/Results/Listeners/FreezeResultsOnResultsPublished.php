<?php

declare(strict_types=1);

namespace Rominas\Results\Listeners;

use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Events\EditionTransitioned;
use Rominas\Results\Actions\PublishEditionResultsAction;

/**
 * Freezes the edition's final results snapshot the moment it enters `results_published`. Runs
 * synchronously so the snapshot exists as soon as the transition returns; the action is idempotent, and
 * if it ever fails the read path falls back to live Scoring computation. Wired in EventServiceProvider.
 */
class FreezeResultsOnResultsPublished
{
    public function __construct(
        private readonly PublishEditionResultsAction $action,
    ) {}

    public function handle(EditionTransitioned $event): void
    {
        if ($event->to !== EditionStatus::ResultsPublished) {
            return;
        }

        $this->action->execute($event->edition);
    }
}
