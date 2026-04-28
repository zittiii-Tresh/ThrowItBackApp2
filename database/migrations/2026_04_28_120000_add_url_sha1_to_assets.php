<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores sha1(url) directly on assets so ArchiveController can look up by
 * indexed column instead of `whereRaw('SHA1(url) = ?')`. Two reasons:
 *
 *   1. SQLite has no built-in SHA1() function, so the raw query breaks the
 *      portable/SQLite distribution path. Storing the hash makes the lookup
 *      database-agnostic.
 *
 *   2. Indexed equality is faster than a function call across every row,
 *      even on MySQL — it's a perf win on the existing setup too.
 *
 * Backfilling existing rows happens in the artisan command
 * `archive:backfill-url-sha1` so the migration itself stays cheap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $t) {
            // char(40) — sha1 hex is exactly 40 chars. Nullable for safe
            // backfill; the artisan command populates it for existing rows.
            $t->char('url_sha1', 40)->nullable()->after('url');
            $t->index(['snapshot_id', 'url_sha1']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $t) {
            $t->dropIndex(['snapshot_id', 'url_sha1']);
            $t->dropColumn('url_sha1');
        });
    }
};
