<?php

namespace Database\Seeders;

use App\Enums\CrawlStatus;
use App\Enums\EventLevel;
use App\Enums\TriggerSource;
use App\Models\CrawlRun;
use App\Models\Site;
use App\Models\SystemEvent;
use Illuminate\Database\Seeder;

/**
 * Populates CrawlRun + SystemEvent tables with plausible-looking demo data
 * so the admin screens (dashboard stats, history log, notifications feed)
 * render something realistic on a fresh clone.
 *
 * Real crawls via `php artisan crawl:run` still work on top of this data —
 * the seeder is idempotent-ish (wipes + reseeds the demo subset).
 */
class DemoCrawlDataSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed demo data for the proposal sites — don't touch real
        // crawls against tmdbportfolio or any site the user added manually.
        $demoSiteUrls = [
            'https://acme.com',
            'https://blog.acme.com',
            'https://shop.acme.com',
            'https://docs.acme.com',
            'https://portal.acme.com',
        ];
        $sites = Site::whereIn('base_url', $demoSiteUrls)->get()->keyBy('base_url');

        // Clear any prior demo data for these sites so reseeding is idempotent.
        CrawlRun::whereIn('site_id', $sites->pluck('id'))->delete();

        foreach ($sites as $site) {
            // Create 4 runs per site — today, yesterday, day-before, and one
            // a week ago — so the dashboard, 30-day window, and history log
            // all have something to show.
            $runs = [
                [now()->subHours(rand(1, 6)),   CrawlStatus::Complete, rand(80, 320)],
                [now()->subDay()->subHours(2), CrawlStatus::Complete, rand(80, 320)],
                [now()->subDays(2),            CrawlStatus::Complete, rand(80, 320)],
                [now()->subDays(7),            CrawlStatus::Complete, rand(80, 320)],
            ];

            // Shop gets a failed run + partial run to exercise the red status badge
            // and the Failed Crawls stat card.
            if ($site->base_url === 'https://shop.acme.com') {
                $runs[0] = [now()->subHours(2), CrawlStatus::Failed, 12];
                $runs[1] = [now()->subDay(),    CrawlStatus::Partial, rand(40, 150)];
            }

            foreach ($runs as [$startedAt, $status, $pages]) {
                $finishedAt = $status === CrawlStatus::Failed
                    ? (clone $startedAt)->addSeconds(rand(20, 60))
                    : (clone $startedAt)->addMinutes(rand(2, 6));

                CrawlRun::create([
                    'site_id'           => $site->id,
                    'status'            => $status,
                    'triggered_by'      => TriggerSource::Scheduler,
                    'started_at'        => $startedAt,
                    'finished_at'       => $finishedAt,
                    'pages_crawled'     => $pages,
                    'assets_downloaded' => $status === CrawlStatus::Failed ? 0 : $pages * rand(4, 8),
                    'storage_bytes'     => $status === CrawlStatus::Failed ? 0 : $pages * 1024 * rand(80, 200),
                    'error_message'     => $status === CrawlStatus::Failed
                        ? 'Connection timeout after 44 seconds'
                        : null,
                    'created_at'        => $startedAt,
                    'updated_at'        => $finishedAt,
                ]);
            }
        }

        // SystemEvent feed — the same events CrawlSiteJob would emit, plus a
        // storage warning to demo the amber dot in the feed.
        SystemEvent::query()->delete();

        SystemEvent::log(
            event: 'crawl.failed',
            message: 'Crawl failed for shop.acme.com — connection timeout after 44 seconds',
            level: EventLevel::Error,
            site: $sites->get('https://shop.acme.com'),
        );
        SystemEvent::log(
            event: 'storage.warning',
            message: 'Storage at 84% capacity (4.2 GB / 50 GB). Consider cleaning old snapshots.',
            level: EventLevel::Warning,
        );
        SystemEvent::log(
            event: 'crawl.partial',
            message: '2 assets missing from acme.com vs previous crawl',
            level: EventLevel::Warning,
            site: $sites->get('https://acme.com'),
        );
        SystemEvent::log(
            event: 'crawl.complete',
            message: 'Crawl completed for docs.acme.com — 201 pages, 940 assets saved',
            level: EventLevel::Info,
            site: $sites->get('https://docs.acme.com'),
        );
        SystemEvent::log(
            event: 'site.added',
            message: 'New site portal.acme.com added — first crawl queued',
            level: EventLevel::Info,
            site: $sites->get('https://portal.acme.com'),
        );

        // Backdate the info + warning events so the feed looks like a timeline,
        // not "everything 5 seconds ago".
        SystemEvent::latest()->get()->each(function ($e, int $i) {
            $e->update(['created_at' => now()->subHours($i * 6 + rand(0, 2))]);
        });
    }
}
