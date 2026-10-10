<?php

declare(strict_types=1);

namespace Rominas\Notifications\Listeners;

use Rominas\FraudMonitoring\Events\FraudAlertsDetected;
use Rominas\Notifications\Actions\NotifyAdminsWithPermissionAction;
use Rominas\Notifications\Notifications\NewFraudAlerts;

/**
 * Tells the users who review public votes that a detection run recorded new fraud alerts. Wired in
 * EventServiceProvider.
 */
class NotifyFraudMonitorsOfNewAlerts
{
    public function __construct(
        private readonly NotifyAdminsWithPermissionAction $notifyAdmins,
    ) {}

    public function handle(FraudAlertsDetected $event): void
    {
        $this->notifyAdmins->execute('fraudMonitoring', new NewFraudAlerts(
            editionId: $event->edition->id,
            editionName: $event->edition->name,
            newAlertCount: $event->newAlertCount,
        ));
    }
}
