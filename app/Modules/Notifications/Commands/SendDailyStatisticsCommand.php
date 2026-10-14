<?php

declare(strict_types=1);

namespace Rominas\Notifications\Commands;

use Illuminate\Console\Command;
use Rominas\Notifications\Actions\SendDailyStatisticsAction;

/**
 * Sends the daily statistics notification to the admins who can see reports. Scheduled once a day in
 * routes/console.php; does nothing when no edition is in progress.
 */
class SendDailyStatisticsCommand extends Command
{
    protected $signature = 'romias:daily-statistics';

    protected $description = 'Send the previous day\'s statistics of the edition in progress to admins';

    public function handle(SendDailyStatisticsAction $action): int
    {
        $recipients = $action->execute();

        $this->info("Daily statistics sent to {$recipients} user(s).");

        return self::SUCCESS;
    }
}
