<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('runs every database session in UTC whatever the server time zone is', function (): void {
    DB::purge();

    $sessionTimeZone = DB::selectOne('select @@session.time_zone as time_zone')->time_zone;

    expect($sessionTimeZone)->toBe('+00:00');
});
