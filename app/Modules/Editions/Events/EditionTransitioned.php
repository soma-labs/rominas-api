<?php

declare(strict_types=1);

namespace Rominas\Editions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;

/**
 * Fired after an edition's lifecycle status changes (see TransitionEditionAction). Cross-module
 * side-effects hang off this — e.g. the Results module freezes the snapshot on `results_published`.
 * Listeners are wired explicitly in EventServiceProvider (auto-discovery does not scan app/Modules).
 */
readonly class EditionTransitioned
{
    use Dispatchable;

    public function __construct(
        public Edition $edition,
        public EditionStatus $from,
        public EditionStatus $to,
    ) {}
}
