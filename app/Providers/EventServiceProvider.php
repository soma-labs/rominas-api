<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Rominas\Academy\Member\Events\MemberActivated;
use Rominas\Academy\MemberProposal\Events\MemberProposalCreated;
use Rominas\Editions\Events\EditionTransitioned;
use Rominas\FraudMonitoring\Events\FraudAlertsDetected;
use Rominas\Notifications\Listeners\NotifyAdminsOfMemberJoined;
use Rominas\Notifications\Listeners\NotifyAdminsOfMemberProposal;
use Rominas\Notifications\Listeners\NotifyFraudMonitorsOfNewAlerts;
use Rominas\Notifications\Listeners\NotifyMembersOfNominationsOpened;
use Rominas\Results\Listeners\FreezeResultsOnResultsPublished;

/**
 * Explicit event→listener wiring. Module listeners live under `app/Modules`, which Laravel's
 * auto-discovery does not scan, so they are registered here.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        EditionTransitioned::class => [
            FreezeResultsOnResultsPublished::class,
            NotifyMembersOfNominationsOpened::class,
        ],
        MemberProposalCreated::class => [
            NotifyAdminsOfMemberProposal::class,
        ],
        MemberActivated::class => [
            NotifyAdminsOfMemberJoined::class,
        ],
        FraudAlertsDetected::class => [
            NotifyFraudMonitorsOfNewAlerts::class,
        ],
    ];
}
