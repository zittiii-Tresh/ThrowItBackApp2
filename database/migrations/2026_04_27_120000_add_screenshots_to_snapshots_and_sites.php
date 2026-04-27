<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds full-page screenshot capture alongside the existing HTML snapshot.
 *
 * - `sites.capture_screenshots` toggles screenshot capture per-site (default
 *   on — screenshots are the visual fallback when HTML rewriting can't
 *   perfectly reproduce the layout).
 * - `snapshots.screenshot_file_id` points into the same `asset_files` dedup
 *   pool the existing assets use, so identical screenshots across runs are
 *   stored once and ref-counted exactly like other archived bytes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $t) {
            $t->boolean('capture_screenshots')
              ->default(true)
              ->after('max_pages');
        });

        Schema::table('snapshots', function (Blueprint $t) {
            $t->foreignId('screenshot_file_id')
              ->nullable()
              ->after('html_path')
              ->constrained('asset_files')
              ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $t) {
            $t->dropForeign(['screenshot_file_id']);
            $t->dropColumn('screenshot_file_id');
        });

        Schema::table('sites', function (Blueprint $t) {
            $t->dropColumn('capture_screenshots');
        });
    }
};
