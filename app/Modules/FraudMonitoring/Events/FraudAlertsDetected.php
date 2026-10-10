<?php

declare(strict_types=1);

namespace Rominas\FraudMonitoring\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Rominas\Editions\Model\Edition;

/**
 * Fired after a detection run that recorded at least one new alert for the edition (see
 * DetectVotingFraudAction). An alert that was only refreshed does not count. Listeners are wired
 * explicitly in EventServiceProvider (auto-discovery does not scan app/Modules).
 */
readonly class FraudAlertsDetected
{
    use Dispatchable;

    public function __construct(
        public Edition $edition,
        public int $newAlertCount,
    ) {}
}
