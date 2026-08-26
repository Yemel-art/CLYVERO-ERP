<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    /** @var \Illuminate\Console\Command $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled tasks will be registered here as the application grows
 * (e.g. tuition reminders, daily backups, archived-year cleanup).
 */
