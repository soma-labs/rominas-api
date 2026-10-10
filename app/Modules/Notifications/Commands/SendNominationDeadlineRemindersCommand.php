<?php

declare(strict_types=1);

namespace Rominas\Notifications\Commands;

use Illuminate\Console\Command;
use Rominas\Notifications\Actions\SendNominationDeadlineRemindersAction;

/**
 * Reminds academy members who have not submitted their nominations that the deadline is near.
 * Scheduled once a day in routes/console.php; sends only on the configured days before the deadline.
 */
class SendNominationDeadlineRemindersCommand extends Command
{
    protected $signature = 'notifications:nomination-reminders';

    protected $description = 'Remind academy members without submitted nominations of the nomination deadline';

    public function handle(SendNominationDeadlineRemindersAction $action): int
    {
        $reminded = $action->execute();

        $this->info("Nomination deadline reminder sent to {$reminded} member(s).");

        return self::SUCCESS;
    }
}
