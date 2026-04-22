<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 |--------------------------------------------------------------------------
 | SiteArchive scheduled tasks
 |--------------------------------------------------------------------------
 |
 | The scheduler ticks once a minute and dispatches CrawlSiteJob for any
 | site whose next_run_at has passed. `withoutOverlapping` prevents the
 | tick from stacking up if a previous dispatch is still running.
 |
 | Run the scheduler locally with:
 |     php artisan schedule:work
 |
 | In production, add a cron entry per the Laravel docs:
 |     * * * * * cd /path/to/site-archive && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('crawl:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping(5);
// Intentionally NOT ->runInBackground() — that spawns a visible cmd.exe
// window on Windows every minute. Our command finishes in a few hundred
// milliseconds (it just queries due sites and popen-spawns detached
// crawl processes), so running it inline in the scheduler's own php-win
// process is plenty fast and completely silent.
