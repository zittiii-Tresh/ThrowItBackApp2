<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row `settings` table driving Admin Screen 7 (Settings).
     *
     * Only one row ever exists; Setting::current() fetches it (creating
     * defaults on first access). A key/value-per-row design was rejected
     * because the values are strongly-typed booleans/enums and we want
     * schema-level type safety, not stringly-typed.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Storage
            $table->string('storage_driver', 16)->default('local');     // "local" | "s3"
            $table->string('retention_policy', 16)->default('all');     // "all" | "90_days" | "30_days"
            $table->unsignedInteger('storage_limit_gb')->default(50);

            // Notifications
            $table->text('email_recipients')->nullable();               // comma-separated
            $table->string('slack_webhook_url', 500)->nullable();
            $table->boolean('notify_on_crawl_failure')->default(true);
            $table->boolean('notify_on_storage_warning')->default(true);
            $table->boolean('notify_on_crawl_success')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
