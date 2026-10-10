<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Timezone of the scheduled notifications
    |--------------------------------------------------------------------------
    |
    | The API stores and sends UTC, but the scheduled notifications follow the
    | Romanian day: the send times below, "yesterday" in the daily statistics,
    | the days left until the nomination deadline, and the deadline printed in
    | member e-mails are all in this zone.
    |
    */

    'timezone' => 'Europe/Bucharest',

    /*
    |--------------------------------------------------------------------------
    | Send times (in the timezone above)
    |--------------------------------------------------------------------------
    |
    | When routes/console.php runs the two scheduled notifications each day.
    |
    */

    'daily_statistics_time' => '08:00',

    'nomination_reminder_time' => '10:00',

    /*
    |--------------------------------------------------------------------------
    | Nomination deadline reminders
    |--------------------------------------------------------------------------
    |
    | How many calendar days before the nomination deadline a member who has
    | not submitted their nominations is reminded. One reminder per entry.
    | Used by SendNominationDeadlineRemindersAction.
    |
    */

    'nomination_reminder_days' => [3, 1],

];
