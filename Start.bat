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
)

REM Make sure caches are fresh — config/route changes between launches.
"%PHP_CLI%" artisan config:clear >nul 2>&1
"%PHP_CLI%" artisan route:clear >nul 2>&1

REM Launch the scheduler in a hidden background process. schedule:work loops
REM internally and dispatches due crawls every minute — replaces the Windows
REM Task Scheduler entry. Killed automatically when this window closes.
echo [scheduler] starting in background
start "SiteArchive scheduler" /B "%PHP_BIN%" artisan schedule:work

REM Open the default browser to the public landing page after a short delay
REM so the dev server has time to bind the port.
echo [browser] opening http://127.0.0.1:8000 in 2 seconds
start "" /B cmd /c "timeout /t 2 >nul && start http://127.0.0.1:8000"

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
