<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('history:prune')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping();

Schedule::command('form-submissions:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();
