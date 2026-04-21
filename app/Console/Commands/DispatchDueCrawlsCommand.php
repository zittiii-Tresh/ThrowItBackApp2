<?php

namespace App\Console\Commands;

use App\Enums\TriggerSource;
use App\Jobs\CrawlSiteJob;
use App\Models\Site;
use Illuminate\Console\Command;

/**
 * Run every minute by the Laravel Scheduler (see routes/console.php).
 *
 * Finds every active site whose next_run_at has passed and dispatches
 * CrawlSiteJob for each. After dispatch we null out next_run_at so the
 * next minute's tick doesn't re-dispatch the same site — the job will
 * set a fresh next_run_at when it completes, via Schedule::nextRunFor().
 *
 * If a crawl fails, next_run_at stays null and the site shows "— not
 * scheduled —" in the admin. Admins then investigate and either click
 * "Crawl now" manually or flip the active toggle off.
 */
class DispatchDueCrawlsCommand extends Command
{
    protected $signature   = 'crawl:dispatch-due {--dry-run : show what would be dispatched without actually queueing}';
    protected $description = 'Dispatch CrawlSiteJob for every site whose next_run_at has passed';

    public function handle(): int
    {
        $due = Site::dueNow()->get();

        if ($due->isEmpty()) {
            $this->line('[crawl:dispatch-due] no sites due');
            return self::SUCCESS;
        }

        $dispatched = [];
        foreach ($due as $site) {
            if ($this->option('dry-run')) {
                $dispatched[] = "would dispatch #{$site->id} {$site->name}";
                continue;
            }

            // Clear next_run_at BEFORE dispatch so the next scheduler tick
            // (usually 60 seconds later) won't double-dispatch if the job
            // is still running.
            $site->update(['next_run_at' => null]);

            CrawlSiteJob::dispatch($site->id, TriggerSource::Scheduler);
            $dispatched[] = "dispatched #{$site->id} {$site->name}";
        }

        foreach ($dispatched as $line) {
            $this->info("[crawl:dispatch-due] $line");
        }

        return self::SUCCESS;
    }
}
