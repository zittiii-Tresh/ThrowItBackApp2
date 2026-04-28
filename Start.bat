@echo off
REM ========================================================================
REM SiteArchive launcher — starts the local server + scheduler, opens browser
REM ========================================================================
REM Designed for Windows portable / desktop-app-style runs. Closing this
REM window stops the server and pauses scheduled crawls until next launch.
REM
REM Expects either:
REM   - PHP and Node already on PATH (Laragon dev box style), OR
REM   - Bundled portable PHP + Node alongside this file (laptop / installer)
REM ========================================================================

setlocal
cd /d "%~dp0"
title SiteArchive

REM Pick portable PHP if it's bundled, else fall back to PATH.
if exist "%~dp0php\php-win.exe" (
    set "PHP_BIN=%~dp0php\php-win.exe"
    set "PHP_CLI=%~dp0php\php.exe"
) else (
    set "PHP_BIN=php-win"
    set "PHP_CLI=php"
)

REM First-run setup: copy .env.example to .env and generate a key if absent.
if not exist "%~dp0.env" (
    echo [first-run] creating .env from .env.example
    copy /Y "%~dp0.env.example" "%~dp0.env" >nul
    "%PHP_CLI%" artisan key:generate --force
)

REM First-run setup: create the SQLite file if absent and run migrations.
if not exist "%~dp0database\database.sqlite" (
    echo [first-run] creating SQLite database
    type nul > "%~dp0database\database.sqlite"
    "%PHP_CLI%" artisan migrate --force
    echo [first-run] creating default admin user (admin@sitesatscale.com / admin123)
    "%PHP_CLI%" artisan tinker --execute="\App\Models\User::firstOrCreate(['email' => 'admin@sitesatscale.com'], ['name' => 'Admin', 'password' => bcrypt('admin123'), 'email_verified_at' => now()]);"
)

REM Make sure caches are fresh — config/route changes between launches.
"%PHP_CLI%" artisan config:clear >nul 2>&1
"%PHP_CLI%" artisan route:clear >nul 2>&1

REM Launch the scheduler in a hidden background process. sitearchive:loop is
REM our in-process replacement for `schedule:work` — fires schedule:run via
REM $this->call() (in-process) instead of a Symfony Process subprocess that
REM Windows wraps in cmd.exe. Net effect: zero cmd flashes per minute.
echo [scheduler] starting in background
start "SiteArchive scheduler" /B "%PHP_BIN%" artisan sitearchive:loop

REM Open the default browser to the public landing page after a short delay
REM so the dev server has time to bind the port. Using PowerShell with
REM -WindowStyle Hidden so no cmd window flashes during startup.
echo [browser] opening http://127.0.0.1:8000 in ~2 seconds
start "" /B powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Seconds 2; Start-Process 'http://127.0.0.1:8000'"

REM Run the dev server in the foreground. Ctrl+C or closing this window
REM stops everything.
echo [server] starting on http://127.0.0.1:8000
echo.
echo ============================================================
echo  SiteArchive is running. Close this window to stop.
echo  Public:  http://127.0.0.1:8000/
echo  Admin:   http://127.0.0.1:8000/admin
echo ============================================================
echo.
"%PHP_CLI%" artisan serve --host=127.0.0.1 --port=8000

endlocal
