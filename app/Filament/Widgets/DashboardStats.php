<?php

namespace App\Filament\Widgets;

use App\Enums\CrawlStatus;
use App\Models\CrawlRun;
use App\Models\Site;
use App\Models\Snapshot;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard Screen 1 — four stat cards across the top:
 *   Total sites | Snapshots (30d) | Storage used | Failed crawls
 *
 * Numbers come directly from the DB — no caching yet since sites/runs
 * tables stay small. Add a `->cacheable(60)` in Phase 7 if needed.
 */
class DashboardStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Sites — active only (paused don't count toward "stuff we're archiving").
        $totalSites = Site::active()->count();

        // Snapshots in the last 30 days across all sites.
        $recentSnapshots = Snapshot::where('created_at', '>=', now()->subDays(30))->count();

        // Total bytes written across all crawl runs, converted to GB/MB.
        $totalBytes = (int) CrawlRun::sum('storage_bytes');
        $storageHuman = $totalBytes > 1024 ** 3
            ? number_format($totalBytes / 1024 ** 3, 1) . ' GB'
            : number_format($totalBytes / 1024 ** 2, 1) . ' MB';

        // Failed crawls in the last 30 days — highlighted red when non-zero
        // (proposal PDF explicitly calls this out).
        $failedCount = CrawlRun::where('status', CrawlStatus::Failed)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            Stat::make('Total sites', $totalSites)
                ->description('Active, scheduled')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary'),

            Stat::make('Snapshots (30d)', number_format($recentSnapshots))
                ->description('Pages captured this month')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('Storage used', $storageHuman)
                ->description('All archived content')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('success'),

            Stat::make('Failed crawls', $failedCount)
                ->description($failedCount > 0 ? 'Needs attention' : 'All healthy')
                ->descriptionIcon($failedCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failedCount > 0 ? 'danger' : 'success'),
        ];
    }
}
