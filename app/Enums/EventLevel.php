<?php

namespace App\Enums;

/**
 * Severity of a SystemEvent. Determines the colored dot + text color
 * shown in the notifications feed (Admin Screen 6) per the proposal PDF.
 */
enum EventLevel: string
{
    case Info    = 'info';     // green — successful operations
    case Warning = 'warning';  // amber — storage warnings, mild issues
    case Error   = 'error';    // red   — crawl failures, fatal problems

    public function label(): string
    {
        return match ($this) {
            self::Info    => 'Info',
            self::Warning => 'Warning',
            self::Error   => 'Error',
        };
    }

    /** Tailwind-friendly color name for the dot. */
    public function color(): string
    {
        return match ($this) {
            self::Info    => 'emerald',
            self::Warning => 'amber',
            self::Error   => 'rose',
        };
    }
}
