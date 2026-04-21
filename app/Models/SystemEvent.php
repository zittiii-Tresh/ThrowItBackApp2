<?php

namespace App\Models;

use App\Enums\EventLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One observable event in the system-wide feed (Admin Screen 6).
 *
 * Written by:
 *   - CrawlSiteJob::handle()  — "crawl.complete" / "crawl.failed"
 *   - StorageMonitorCommand   — "storage.warning" (Phase 7 when wired)
 *   - Site model saving hook  — "site.added" (Phase 5f when wired)
 *
 * @property int $id
 * @property string $event
 * @property EventLevel $level
 * @property string $message
 * @property int|null $site_id
 * @property int|null $crawl_run_id
 * @property \Illuminate\Support\Carbon|null $read_at
 */
class SystemEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event',
        'level',
        'message',
        'site_id',
        'crawl_run_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'level'   => EventLevel::class,
            'read_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function crawlRun(): BelongsTo
    {
        return $this->belongsTo(CrawlRun::class);
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    /**
     * Convenience factory. Keeps call sites tidy — no manual array plumbing.
     *
     *   SystemEvent::log('crawl.complete', "acme.com — 312 pages, 1840 assets",
     *       level: EventLevel::Info, site: $site, run: $run);
     */
    public static function log(
        string $event,
        string $message,
        EventLevel $level = EventLevel::Info,
        ?Site $site = null,
        ?CrawlRun $run = null,
    ): self {
        return self::create([
            'event'        => $event,
            'level'        => $level,
            'message'      => $message,
            'site_id'      => $site?->id,
            'crawl_run_id' => $run?->id,
        ]);
    }
}
