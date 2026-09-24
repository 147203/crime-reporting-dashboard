<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Uploaded files may hold rejected personal data; abandoned dry runs are closed hourly (needs `php artisan schedule:work`
// or a cron entry running `php artisan schedule:run` every minute).
Schedule::command('imports:prune')->hourly();
