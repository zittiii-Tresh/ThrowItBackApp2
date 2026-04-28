# SiteArchive — Portable / desktop-app build

This branch of SiteArchive is configured to run as a self-contained app on
a single Windows machine, with **SQLite** instead of MySQL and the
**database** driver for cache + sessions instead of Redis. Closing the
launcher stops the server and pauses scheduled crawls until next launch —
the trade-off for not needing background services running on the host.

For the full development setup (Laragon / MySQL / Redis / Windows Task
Scheduler), see [`SETUP.md`](./SETUP.md).

---

## How it runs

`Start.bat` is the entry point. It:

1. On first run, copies `.env.example` → `.env`, generates an APP_KEY,
   creates `database/database.sqlite`, and runs migrations.
2. Spawns `php artisan schedule:work` in the background — this is the
   scheduler that fires due crawls every minute. Replaces the Windows
   Task Scheduler entry from the dev setup.
3. Opens the default browser to http://127.0.0.1:8000.
4. Runs `php artisan serve` in the foreground. Ctrl+C or closing the
   window stops the server, the scheduler, and any in-flight crawls.

---

## Two run modes

### Mode A — bundled (the laptop / installer scenario)

`Start.bat` looks for portable PHP + Node alongside itself:

```
SiteArchive/
├── php/                ← portable PHP 8.3 (php.exe + php-win.exe)
├── node/               ← portable Node 22 + npm.cmd
├── chrome/             ← Puppeteer's bundled Chromium for Browsershot
├── vendor/             ← pre-installed; no composer at runtime
├── node_modules/       ← pre-installed; no npm at runtime
├── public/build/       ← pre-built CSS/JS
├── database/database.sqlite
├── Start.bat
└── (the rest of the Laravel codebase)
```

`.env` should set `ARCHIVE_NODE_BINARY=./node/node.exe` and
`ARCHIVE_NPM_BINARY=./node/npm.cmd` so Browsershot uses the bundled
runtime instead of system PATH.

### Mode B — dev box (PHP + Node already on PATH)

If `Start.bat` doesn't find the bundled `php\` folder, it falls through
to whatever `php-win` and `php` are on PATH. Use this on the dev machine
when you just want a quick portable-style launch without the bundle.

---

## What's different from the dev setup

| | Dev setup (`SETUP.md`) | Portable (this file) |
|---|---|---|
| Database | MySQL 8 | SQLite (single file) |
| Cache | Redis | DB-backed |
| Sessions | Redis | DB-backed |
| Scheduler | Windows Task Scheduler (S4U logon) | `php artisan schedule:work` running while launcher is open |
| Install steps | ~30 min (Laragon, composer, npm, migrate, Task Scheduler) | Double-click `Start.bat` |
| Crawls fire when laptop is asleep? | Yes (Task Scheduler fires when machine is on) | No (scheduler only runs while launcher is open) |
| Concurrent users | Many (proper web stack) | One (single-threaded `artisan serve`) |

---

## Bringing data over from the dev box

The portable build starts with an empty SQLite database — your existing
TEST / TEST 2 / Mancinis / 5ELK / Leighton crawls live in MySQL on the
dev box and don't auto-migrate.

If you want history copied:

```bash
# On the dev box — dump as raw INSERTs (not MySQL-specific syntax)
mysqldump --compatible=ansi --no-create-info --skip-extended-insert \
  -u root site_archive > sa-data.sql

# Copy sa-data.sql + storage/app/snapshots/ to the laptop

# On the laptop — first run Start.bat once so SQLite + tables exist, then:
sqlite3 database/database.sqlite < sa-data.sql
xcopy /E /I sa-storage storage\app\snapshots
```

The dedup pool layout (`_pool/{ab}/{cd}/{hash}.{ext}`) is platform-
agnostic so the storage transfer is byte-for-byte.

---

## Building the installer (`.exe`)

Building `SiteArchive-Setup.exe` is a separate exercise — see
`build-installer.ps1` (TODO: not yet written) for the bundle assembly
pipeline. The .exe is the polish layer on top of this portable structure;
the working `Start.bat`-based portable bundle is what the .exe wraps.
