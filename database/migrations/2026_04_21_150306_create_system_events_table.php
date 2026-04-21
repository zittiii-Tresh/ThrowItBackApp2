<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `system_events` — global event feed shown on Admin Screen 6
     * (Notifications). Each row is one observable thing that happened:
     * crawl complete, crawl failed, storage warning, new site added, etc.
     *
     * Distinct from Laravel's per-user `notifications` table — these are
     * not user-scoped, they're system-wide and every admin sees the same
     * feed.
     */
    public function up(): void
    {
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();

            // Machine name for filtering / grouping. Examples:
            //   "crawl.complete", "crawl.failed", "storage.warning",
            //   "site.added", "site.paused"
            $table->string('event', 40);

            // "info" (green), "warning" (amber), "error" (red).
            // Maps 1:1 to the colored dot on the notifications feed.
            $table->string('level', 16)->default('info');

            // Human-readable line shown in the feed.
            $table->string('message', 500);

            // Optional related site — lets the UI link back to sites/{id}.
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();

            // Optional related crawl run — for "view run" actions.
            $table->foreignId('crawl_run_id')->nullable()->constrained('crawl_runs')->nullOnDelete();

            // When an admin clicks an item, we flip read_at = now().
            // Unread count drives the sidebar badge.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['level', 'created_at']);
            $table->index(['read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_events');
    }
};
