<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site override for the renderer's image-wait timeout. Null means
 * "use the global archive.renderer.settle_ms" (default 20000ms). Set
 * higher for slow staging hosts whose images consistently miss the
 * default ceiling — the override raises the ceiling for THAT site only,
 * so fast sites aren't dragged down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $t) {
            $t->unsignedInteger('settle_ms_override')
              ->nullable()
              ->after('capture_screenshots');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $t) {
            $t->dropColumn('settle_ms_override');
        });
    }
};
