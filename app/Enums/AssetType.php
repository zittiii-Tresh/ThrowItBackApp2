<?php

namespace App\Enums;

/**
 * Asset categories captured during a crawl. Matches the 4 rows in the
 * "Asset types captured" table in the proposal PDF (Screen 4 — Assets).
 */
enum AssetType: string
{
    case Image      = 'image';
    case Stylesheet = 'stylesheet';
    case Javascript = 'javascript';
    case Font       = 'font';
    case Other      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Image      => 'Image',
            self::Stylesheet => 'CSS',
            self::Javascript => 'JS',
            self::Font       => 'Font',
            self::Other      => 'Other',
        };
    }

    /**
     * Classify an asset into one of our buckets. Prefers the HTTP
     * Content-Type header, falls back to the URL's file extension
     * when the mime is missing ("") or generic ("application/octet-stream"),
     * which happens often on CDN-served fonts.
     */
    public static function fromMimeType(?string $mime, ?string $url = null): self
    {
        if ($mime) {
            $cleaned = strtolower(strtok($mime, ';'));   // strip charset suffix
            $byMime = match (true) {
                str_starts_with($cleaned, 'image/')                                  => self::Image,
                $cleaned === 'text/css'                                              => self::Stylesheet,
                in_array($cleaned, ['application/javascript', 'text/javascript',
                                    'application/x-javascript', 'text/ecmascript'], true)
                                                                                      => self::Javascript,
                str_starts_with($cleaned, 'font/') ||
                in_array($cleaned, ['application/font-woff', 'application/font-woff2',
                                    'application/vnd.ms-fontobject',
                                    'application/x-font-ttf'], true)                  => self::Font,
                default                                                               => null,
            };
            if ($byMime !== null) {
                return $byMime;
            }
        }

        // Mime was missing or generic (application/octet-stream) — try the URL.
        if ($url) {
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            return match ($ext) {
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif', 'bmp'  => self::Image,
                'css'                                                              => self::Stylesheet,
                'js', 'mjs'                                                        => self::Javascript,
                'woff', 'woff2', 'ttf', 'otf', 'eot'                               => self::Font,
                default                                                            => self::Other,
            };
        }

        return self::Other;
    }
}
