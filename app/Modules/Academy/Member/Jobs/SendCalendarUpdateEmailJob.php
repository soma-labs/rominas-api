<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Rominas\Delivery\Actions\DeliveryAction;

/**
 * Sends the calendar update to one member, so a failed send retries only that member.
 */
class SendCalendarUpdateEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $email) {}

    public function handle(DeliveryAction $delivery): void
    {
        $delivery->execute('academy-calendar-update-email', ['email'], $this->email);
    }
}
