<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run by the server's cron every minute: `php artisan schedule:run` (see README).
Schedule::command('app:send-reminders')->everyMinute()->withoutOverlapping();
