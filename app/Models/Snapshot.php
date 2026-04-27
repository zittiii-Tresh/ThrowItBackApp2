<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One captured page within a CrawlRun. HTML lives on disk; this row is
 * the searchable metadata.
 *
 * @property int $id
 * @property int $crawl_run_id
 * @property string $url
 * @property string $path
 * @property int $status_code
 * @property string|null $title
 * @property string $html_path
 * @property int|null $screenshot_file_id
 * @property int $asset_count
 * @property int $html_bytes
 */
class Snapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'crawl_run_id',
        'url',
        'path',
        'status_code',
        'title',
        'html_path',
        'screenshot_file_id',
        'asset_count',
        'html_bytes',
    ];

    protected function casts(): array
    {
        return [
            'status_code'        => 'integer',
            'asset_count'        => 'integer',
            'html_bytes'         => 'integer',
            'screenshot_file_id' => 'integer',
        ];
    }

    public function crawlRun(): BelongsTo
    {
        return $this->belongsTo(CrawlRun::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * The pooled screenshot file (JPEG) captured alongside the HTML.
     * Nullable — a snapshot may have no screenshot if the site has
     * `capture_screenshots = false` or Browsershot failed mid-run.
     */
    public function screenshotFile(): BelongsTo
    {
        return $this->belongsTo(AssetFile::class, 'screenshot_file_id');
    }

    /** Loads the stored HTML body from the archive disk. */
    public function readHtml(): string
    {
        return Storage::disk('archive')->get($this->html_path) ?? '';
    }

    /**
     * Mirror Asset's deleting hook — when a Snapshot is deleted (cascade
     * from CrawlRun forceDelete, or manual purge), drop the ref-count on
     * its screenshot pool entry so the JPEG can be garbage-collected when
     * no other snapshot references it.
     *
     * Asset rows have their own `deleting` hook for asset_file refs, so
     * we only need to handle the screenshot one here.
     */
    protected static function booted(): void
    {
        static::deleting(function (Snapshot $snapshot): void {
            if ($snapshot->screenshot_file_id) {
                $snapshot->screenshotFile?->releaseRef();
            }
        });
    }
}
