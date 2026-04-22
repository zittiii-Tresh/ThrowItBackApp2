<?php

namespace App\Filament\Pages;

use App\Models\SystemEvent;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Admin Screen 6 — Notifications feed.
 *
 * Reads from the `system_events` table. Each row renders as a colored-dot
 * item with message + timestamp. Clicking an item marks it read; older
 * read items dim.
 *
 * Sidebar badge shows unread count.
 */
class Notifications extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifications';
    protected static ?int    $navigationSort  = 30;

    protected static string $view = 'filament.pages.notifications';

    public function getTitle(): string
    {
        return 'Notifications';
    }

    /**
     * Feed is scoped to crawl failures only — the Notifications screen is
     * meant to surface things an admin needs to act on. Successful crawls,
     * storage warnings, "site added" info events etc. still get written
     * to system_events (they may be useful for analytics or a future
     * activity log), but they don't clutter this page.
     */
    protected static function failureQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return SystemEvent::query()->where('event', 'crawl.failed');
    }

    /** Sidebar badge — unread crawl failures, empty when zero. */
    public static function getNavigationBadge(): ?string
    {
        $n = self::failureQuery()->whereNull('read_at')->count();
        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // Always danger — this feed only ever contains failures.
        return 'danger';
    }

    /** Newest failed crawls first, capped to 100. */
    public function getEvents(): Collection
    {
        return self::failureQuery()
            ->with(['site', 'crawlRun'])
            ->latest()
            ->limit(100)
            ->get();
    }

    /** Livewire action: flip one event's read_at = now. */
    public function markRead(int $eventId): void
    {
        SystemEvent::where('id', $eventId)->update(['read_at' => now()]);
    }

    /** Livewire action: mark all unread as read. */
    public function markAllRead(): void
    {
        $count = SystemEvent::unread()->update(['read_at' => now()]);

        FilamentNotification::make()
            ->title("Marked {$count} notification" . ($count === 1 ? '' : 's') . " as read")
            ->success()
            ->send();
    }
}
