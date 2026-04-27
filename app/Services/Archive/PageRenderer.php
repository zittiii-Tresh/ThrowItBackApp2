<?php

namespace App\Services\Archive;

use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Spatie\Browsershot\Enums\Polling;

/**
 * Wraps Browsershot to render a page in real Chromium and return either
 * the post-render DOM or a full-page JPEG.
 *
 * Both render paths share a single configuredShot() that handles the
 * "consistency-killing" lazy-load problem in three layers:
 *
 *   1. Chrome --disable-features=LazyImageLoading kills the browser's
 *      native lazy loading behaviour entirely (the loading="lazy" attr
 *      is ignored). This alone eliminates a whole category of "wait
 *      for scroll to fetch."
 *
 *   2. addScriptTag injects in-page JS that swaps data-src / data-srcset
 *      patterns into real src / srcset attrs (covers WP / generic JS
 *      lazy loaders), then progressively scrolls in viewport-step
 *      chunks via requestAnimationFrame to fire IntersectionObserver-
 *      based loaders.
 *
 *   3. waitForFunction polls the page until EVERY <img> is complete
 *      (loaded or errored) before the capture fires. This replaces the
 *      old fixed setDelay — fast pages stay fast, slow pages wait
 *      exactly as long as they need, with a hard timeout ceiling so a
 *      single dead image can't hang the crawl.
 *
 * Falls back gracefully: if Chromium fails for any reason, the call
 * returns null and the caller (observer) decides whether to fall back
 * to the raw response body / skip the screenshot.
 */
class PageRenderer
{
    public function __construct(protected ?array $cfg = null)
    {
        $this->cfg = $cfg ?? config('archive.renderer');
    }

    /**
     * Returns true if we should use Browsershot for this run.
     */
    public function isBrowsershotEnabled(): bool
    {
        return ($this->cfg['mode'] ?? 'static') === 'browsershot';
    }

    /**
     * Render a URL in headless Chromium and return the post-JS DOM.
     * Returns null on failure (caller falls back to static body).
     *
     * After this call, all `<img>` tags in the returned HTML have real
     * URLs in their `src` attribute — no placeholders, no empty src —
     * because the lazy-load forcing JS promotes data-src → src and
     * waitForFunction blocks until every image has actually loaded.
     *
     * @param  ?int  $settleTimeoutMs  Per-site override for the max image-
     *                                  wait timeout. Falls back to the
     *                                  global settle_ms when null.
     */
    public function renderHtml(string $url, ?int $settleTimeoutMs = null): ?string
    {
        if (! $this->isBrowsershotEnabled()) {
            return null;
        }

        try {
            $html = $this->configuredShot($url, $settleTimeoutMs)->bodyHtml();
            return $html === '' ? null : $html;
        } catch (\Throwable $e) {
            Log::warning('Browsershot render failed; falling back to static', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Capture a full-page JPEG of the URL and return the raw image bytes.
     * Returns null on failure so the caller can simply skip persisting a
     * screenshot for this page rather than failing the whole crawl.
     *
     * @param  ?int  $settleTimeoutMs  Per-site override for the max image-
     *                                  wait timeout. Falls back to the
     *                                  global settle_ms when null.
     */
    public function renderScreenshot(string $url, ?int $settleTimeoutMs = null): ?string
    {
        if (! $this->isBrowsershotEnabled()) {
            return null;
        }

        try {
            $shot = $this->configuredShot($url, $settleTimeoutMs)
                ->setScreenshotType('jpeg', (int) ($this->cfg['screenshot_quality'] ?? 75));

            if (! empty($this->cfg['screenshot_full_page'])) {
                $shot->fullPage();
            }

            $bytes = $shot->screenshot();
            return $bytes === '' ? null : $bytes;
        } catch (\Throwable $e) {
            Log::warning('Browsershot screenshot failed; skipping screenshot for this page', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Shared Browsershot configuration. See class doc for the full
     * three-layer lazy-load story; the meaningful tuning is the
     * waitForFunction timeout, which bounds how long we'll wait for
     * the slowest image on a page.
     */
    protected function configuredShot(string $url, ?int $settleTimeoutMs = null): Browsershot
    {
        $shot = Browsershot::url($url)
            ->setNodeBinary($this->cfg['node_binary'])
            ->setNpmBinary($this->cfg['npm_binary'])
            ->windowSize($this->cfg['viewport_width'], $this->cfg['viewport_height'])
            ->timeout($this->cfg['timeout_seconds'])
            ->waitUntilNetworkIdle(true)
            ->dismissDialogs()
            ->ignoreHttpsErrors();

        // Layer 1: turn off Chrome's native lazy-image loading. With this
        // flag, every <img loading="lazy"> behaves as if it were eager —
        // the browser kicks off requests immediately on parse, regardless
        // of viewport position. Doesn't help with JS-driven lazy patterns
        // (those are layer 2) but eliminates one whole class of timing.
        try {
            $shot->setOption('args', [
                '--disable-features=LazyImageLoading,LazyFrameLoading',
                '--no-sandbox',
            ]);
        } catch (\Throwable $e) {
            Log::debug('Browsershot setOption(args) unavailable', ['error' => $e->getMessage()]);
        }

        // Layer 2: in-page JS that handles JS-driven lazy patterns and
        // triggers IntersectionObserver loaders by progressive scrolling.
        // See class doc.
        $progressiveScrollJs = <<<'JS'
            (function() {
                document.querySelectorAll('img[loading="lazy"]').forEach(i => { i.loading = 'eager'; });
                document.querySelectorAll('img[data-src]').forEach(i => { if (i.dataset.src) i.src = i.dataset.src; });
                document.querySelectorAll('img[data-lazy-src]').forEach(i => { if (i.dataset.lazySrc) i.src = i.dataset.lazySrc; });
                document.querySelectorAll('img[data-original]').forEach(i => { if (i.dataset.original) i.src = i.dataset.original; });
                document.querySelectorAll('img[data-srcset]').forEach(i => { if (i.dataset.srcset) i.srcset = i.dataset.srcset; });
                document.querySelectorAll('source[data-srcset]').forEach(s => { if (s.dataset.srcset) s.srcset = s.dataset.srcset; });

                const step = Math.max(Math.floor(window.innerHeight / 2), 300);
                let pos = 0;
                function scrollTick() {
                    window.scrollTo(0, pos);
                    pos += step;
                    if (pos < (document.body.scrollHeight || 0)) {
                        requestAnimationFrame(scrollTick);
                    } else {
                        setTimeout(() => window.scrollTo(0, 0), 100);
                    }
                }
                requestAnimationFrame(scrollTick);
            })();
        JS;

        try {
            $shot->setOption('addScriptTag', json_encode(['content' => $progressiveScrollJs]));
        } catch (\Throwable $e) {
            Log::debug('Browsershot addScriptTag unavailable; layer 2 skipped', [
                'error' => $e->getMessage(),
            ]);
        }

        // Layer 3: event-driven wait. Polls the page until every <img>
        // element is complete (loaded OR errored — img.complete is true
        // in both cases, so a permanently-broken image won't hang us
        // until the timeout). This is what makes capture timing match
        // actual page state instead of a fixed delay.
        //
        // Per-site override wins; otherwise the global settle_ms config.
        // baseline_ms is a tiny floor so font rendering and animations
        // settle even on already-loaded pages — kept minimal because
        // waitForFunction does the real work.
        $timeoutMs = $settleTimeoutMs ?? (int) ($this->cfg['settle_ms'] ?? 20000);
        $baselineMs = (int) ($this->cfg['baseline_ms'] ?? 500);

        $waitFn = 'document.images.length === 0 || Array.from(document.images).every(i => i.complete)';

        try {
            // Polling::RequestAnimationFrame re-evaluates the condition on
            // every browser paint — far cheaper and more responsive than
            // a millisecond timer. Browsershot's signature is enum-typed
            // (?Polling), not int, so we MUST pass an enum value.
            $shot->waitForFunction($waitFn, Polling::RequestAnimationFrame, $timeoutMs);
        } catch (\Throwable $e) {
            // If waitForFunction itself isn't available (older Browsershot)
            // fall back to a small fixed delay — much better than a 20s
            // setDelay across the board.
            Log::debug('Browsershot waitForFunction unavailable; using baseline delay only', [
                'error' => $e->getMessage(),
            ]);
            $shot->setDelay(max($baselineMs, 2000));
            return $shot;
        }

        if ($baselineMs > 0) {
            $shot->setDelay($baselineMs);
        }

        return $shot;
    }
}
