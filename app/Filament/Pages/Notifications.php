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

    /** Sidebar badge — number of unread events, empty when zero. */
    public static function getNavigationBadge(): ?string
    {
        $n = SystemEvent::unread()->count();
        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // Red when any error-level events are unread.
        return SystemEvent::unread()->where('level', 'error')->exists()
            ? 'danger'
            : 'primary';
    }

    /** Newest events first, capped to 100. */
    public function getEvents(): Collection
    {
        return SystemEvent::query()
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
