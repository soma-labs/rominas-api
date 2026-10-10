<?php

use Illuminate\Support\Facades\Schedule;

// Sweep submitted public ballots for suspicious activity and record fraud alerts (FraudMonitoring).
// Requires the OS cron to run `php artisan schedule:run` every minute in production.
Schedule::command('fraud:detect')->hourly();

// Notifications that follow the Romanian day (Notifications): yesterday's statistics for admins, and
// the nomination deadline reminder for academy members who have not submitted yet.
Schedule::command('notifications:daily-statistics')
    ->dailyAt((string) config('notifications.daily_statistics_time'))
    ->timezone((string) config('notifications.timezone'));

Schedule::command('notifications:nomination-reminders')
    ->dailyAt((string) config('notifications.nomination_reminder_time'))
    ->timezone((string) config('notifications.timezone'));
