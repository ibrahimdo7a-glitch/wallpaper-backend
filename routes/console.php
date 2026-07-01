<?php

use App\Console\Commands\SendDailyReportCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Daily stats digest to super admins — 21:00 Riyadh. Runs via `schedule:work`
// (backgrounded in the Dockerfile); the command self-gates on daily_report_enabled.
Schedule::command('report:daily')
    ->dailyAt('21:00')
    ->timezone(SendDailyReportCommand::TZ);
