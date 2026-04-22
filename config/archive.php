<?php

/**
 * Tunable knobs for the crawl engine and archive playback. All have safe
 * defaults; override via .env for per-environment tuning. Production should
 * be aggressive (own-hosted SAS sites can take it); shaky dev targets like
 * SPA hosts on free Netlify tiers may need the conservative end of the
 * range to avoid 5xx-from-rate-limiting.
 */
return [

    'crawler' => [

        // Parallel HTTP requests in flight. Spatie/Crawler's default is 10;
        // the previous defensive setting was 3 (after partial-failure pain
        // on Netlify SPA cold starts in commit ca26a60). 10 is fine for
        // SAS-owned hosts; drop to 3-5 if a site starts producing partials.
        'concurrency' => (int) env('ARCHIVE_CRAWL_CONCURRENCY', 10),

        // Milliseconds between requests. 0 = full speed (Spatie's default).
        // The old hardcoded 200ms was legacy throttling — at concurrency 10
        // that's a ~2s pause every 10 requests, which dominates wall time
        // on small sites. Set to >0 only if a target host needs it.
        'delay_ms' => (int) env('ARCHIVE_CRAWL_DELAY_MS', 0),

        // Per-request HTTP timeouts (seconds). Generous for SPA cold-starts.
        'timeout'         => (int) env('ARCHIVE_CRAWL_TIMEOUT', 30),
        'connect_timeout' => (int) env('ARCHIVE_CRAWL_CONNECT_TIMEOUT', 10),

        // Max redirects followed per page fetch.
        'max_redirects' => (int) env('ARCHIVE_CRAWL_MAX_REDIRECTS', 3),

        'user_agent' => env('ARCHIVE_CRAWL_USER_AGENT', 'SiteArchiveBot/1.0 (+internal-tool)'),
    ],

    'playback' => [

        // Archived assets are content-addressed by sha1(url) per snapshot —
        // the bytes for a given URL within a given snapshot never change.
        // 1 year + immutable lets the browser skip re-validation entirely.
        'asset_max_age' => (int) env('ARCHIVE_ASSET_MAX_AGE', 31536000),
    ],

];
