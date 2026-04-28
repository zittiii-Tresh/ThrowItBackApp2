<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill: populate `assets.url_sha1` for rows created before the
 * 2026_04_28 migration that added the column. Required because the
 * ArchiveController used to look assets up via `whereRaw('SHA1(url) = ?')`
 * (MySQL-only). The new lookup is by indexed `url_sha1` column — DB-agnostic
 * and faster — but pre-existing rows have NULL there until this command runs.
 *
 * Idempotent and chunked: runs on rows where url_sha1 IS NULL. Re-running
 * after a partial completion picks up where it left off.
 *
 * Usage:
 *   php artisan archive:backfill-url-sha1
 *   php artisan archive:backfill-url-sha1 --chunk=2000   (override chunk size)
 */
class BackfillUrlSha1Command extends Command
{
    protected $signature   = 'archive:backfill-url-sha1
                              {--chunk=1000 : Rows per batch update}';

    protected $description = 'Populate assets.url_sha1 for legacy rows so the indexed lookup works';

    public function handle(): int
    {
        $chunkSize = max((int) $this->option('chunk'), 100);

        $remaining = Asset::whereNull('url_sha1')->count();
        if ($remaining === 0) {
            $this->info('All assets already have url_sha1 populated. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Backfilling url_sha1 on {$remaining} asset row(s)…");
        $bar = $this->output->createProgressBar($remaining);
        $bar->start();

        $updated = 0;

        // Stream rows in chunks. Each chunk runs its own UPDATE per row —
        // not a single bulk UPDATE — because sha1 is computed client-side
        // and varies per row. Wrapped in a transaction so a crash mid-chunk
        // doesn't leave half-updated state.
        Asset::whereNull('url_sha1')
            ->select('id', 'url')
            ->chunkById($chunkSize, function ($chunk) use (&$updated, $bar) {
                DB::transaction(function () use ($chunk, &$updated, $bar) {
                    foreach ($chunk as $row) {
                        if (empty($row->url)) {
                            $bar->advance();
                            continue;
                        }
                        DB::table('assets')
                            ->where('id', $row->id)
                            ->update(['url_sha1' => sha1($row->url)]);
                        $updated++;
                        $bar->advance();
                    }
                });
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Updated {$updated} row(s).");

        return self::SUCCESS;
    }
}
