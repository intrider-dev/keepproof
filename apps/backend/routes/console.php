<?php

use App\Modules\Notifications\Actions\DispatchDueReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(static fn () => app(DispatchDueReminders::class)->execute())
    ->name('dispatch-due-reminders')
    ->everyMinute();
