<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Rominas\Editions\Events\EditionTransitioned;
use Rominas\Results\Listeners\FreezeResultsOnEditionPublished;

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
            FreezeResultsOnEditionPublished::class,
        ],
    ];
}
