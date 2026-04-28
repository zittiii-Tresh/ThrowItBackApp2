<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * In-process replacement for `php artisan schedule:work`.
 *
 * Why this exists: Laravel's stock `schedule:work` ticks every minute by
 * spawning `php artisan schedule:run` as a Symfony Process subprocess.
 * On Windows, Symfony Process wraps the spawn in cmd.exe even when the
 * parent is windowless php-win.exe, which causes a black console window
 * to flash every minute. The portable bundle's Start.bat backgrounds
 * this command, so that flash was firing constantly.
 *
 * This command does the same job — wake on minute boundary, run the
 * scheduler — but calls schedule:run via $this->call() in the SAME
 * process. No subprocess, no cmd.exe wrap, no flash.
 *
 * Trade-off: stock schedule:work isolates each tick in a fresh process,
 * so a fatal error in one scheduled command doesn't crash the loop.
 * Here, an unhandled exception WOULD bring down the loop. We mitigate
 * with a try/catch around the per-tick call so the scheduler keeps
 * ticking even when individual commands throw.
 */
class SchedulerLoopCommand extends Command
{
    protected $signature   = 'sitearchive:loop';
    protected $description = 'Run the Laravel scheduler in-process every minute (Windows-friendly, no cmd.exe spawn)';

    public function handle(): int
    {
        $this->info(now()->toDateTimeString() . ' — sitearchive:loop started');

        // Track last execution to avoid double-firing within a minute.
        $lastTick = null;

        while (true) {
            $now = now();

            // Sleep until the next minute boundary so the tick aligns with
            // Schedule::everyMinute() expectations. Wake at second 0 of
            // each minute, fire once.
            $secondsUntilNextMinute = 60 - $now->second;
            sleep(max(1, $secondsUntilNextMinute));

            $thisTick = now()->startOfMinute();
            if ($lastTick && $lastTick->equalTo($thisTick)) {
                continue;
            }

            try {
                $this->call('schedule:run');
            } catch (\Throwable $e) {
                // Don't let one bad tick kill the whole loop. Log and
                // keep ticking — the next minute will retry.
                $this->error(now()->toDateTimeString() . ' — tick failed: ' . $e->getMessage());
                logger()->error('sitearchive:loop tick failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            $lastTick = $thisTick;
        }

        return self::SUCCESS;
    }
}
