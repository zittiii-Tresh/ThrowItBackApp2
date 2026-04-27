# SiteArchive — setup guide

How to get SiteArchive running on a fresh device. Estimated time: ~15-30 min depending on download speeds.

If you're picking this up to keep building on, also read [`CLAUDE.md`](./CLAUDE.md) for the architecture + decisions.

---

## Prerequisites

| What | Why | Windows | Linux / Mac |
|---|---|---|---|
| **PHP 8.3** | App runtime | [Laragon](https://laragon.org) bundles it | `apt install php8.3` / `brew install php@8.3` |
| **Composer** | PHP package manager | Bundled with Laragon | `apt install composer` / `brew install composer` |
| **Node.js 18+** | Vite + Puppeteer | Bundled with Laragon | `apt install nodejs npm` / `brew install node` |
| **MySQL 8** | Database | Bundled with Laragon | `apt install mysql-server` / `brew install mysql` |
| **Redis** | Cache + sessions | Bundled with Laragon | `apt install redis` / `brew install redis` |
| **Git** | To clone the repo | [git-scm.com](https://git-scm.com) | usually pre-installed |

**Easiest on Windows**: install [Laragon](https://laragon.org) — bundles PHP, Composer, MySQL, Redis, and Node into one installer. The original development machine ran on Laragon and most paths in this guide assume that layout.

---

## 1. Clone + install dependencies

```bash
git clone https://github.com/zittiii-Tresh/ThrowItBack.git
cd ThrowItBack

# Install PHP packages
# Windows:
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
# Linux / Mac:
composer install

# Install Node packages — this also downloads Chromium for Browsershot (~250 MB)
npm install

# Build the frontend CSS/JS bundle
npm run build
```

---

## 2. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` in a text editor and adjust these values:

```env
APP_NAME=SiteArchive
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Database — set to match your local MySQL credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=site_archive
DB_USERNAME=root
DB_PASSWORD=

# Redis — defaults usually work
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# IMPORTANT: Browsershot needs the absolute path to YOUR node binary.
# Find yours with:  where node  (Windows)  or  which node  (Linux/Mac)
# Windows (Laragon default):
ARCHIVE_NODE_BINARY=C:/laragon/bin/nodejs/node-v22/node.exe
ARCHIVE_NPM_BINARY=C:/laragon/bin/nodejs/node-v22/npm.cmd
# Linux example:
# ARCHIVE_NODE_BINARY=/usr/bin/node
# ARCHIVE_NPM_BINARY=/usr/bin/npm
# Mac example:
# ARCHIVE_NODE_BINARY=/opt/homebrew/bin/node
# ARCHIVE_NPM_BINARY=/opt/homebrew/bin/npm

# Crawler defaults — already tuned for WordPress staging hosts
ARCHIVE_CRAWL_CONCURRENCY=3
ARCHIVE_RENDERER=browsershot
```

---

## 3. Set up the database

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE site_archive CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run all migrations (creates ~10 tables incl. dedup pool, retention, trash)
php artisan migrate

# Create your first admin user
php artisan tinker
```

In tinker:
```php
\App\Models\User::create([
    'name'              => 'Your Name',
    'email'             => 'you@example.com',
    'password'          => bcrypt('YourStrongPassword'),
    'email_verified_at' => now(),
]);
exit
```

---

## 4. Start the app

```bash
php artisan serve
```

Visit:
- Public archive viewer: http://127.0.0.1:8000/
- Admin panel: http://127.0.0.1:8000/admin

Log in with the admin account you just created.

---

## 5. Set up the auto-scheduler

This runs `php artisan schedule:run` every minute. Without it, scheduled crawls won't fire and the nightly cleanup won't run.

### Windows — Task Scheduler (recommended)

Open **PowerShell as Administrator** and run (replacing `C:\path\to\ThrowItBack` with your actual path):

```powershell
$action = New-ScheduledTaskAction `
  -Execute "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php-win.exe" `
  -Argument "artisan schedule:run" `
  -WorkingDirectory "C:\path\to\ThrowItBack"

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
  -RepetitionInterval (New-TimeSpan -Minutes 1)

$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" `
  -LogonType S4U -RunLevel Limited

$settings = New-ScheduledTaskSettingsSet -Hidden `
  -ExecutionTimeLimit (New-TimeSpan -Hours 0)

Register-ScheduledTask -TaskName "SiteArchive Scheduler" `
  -Action $action -Trigger $trigger -Principal $principal -Settings $settings
```

> **Critical**: `LogonType S4U` is what prevents a cmd-window from flashing every minute. With `InteractiveToken`, Windows attaches a console to `php-win.exe` even though it's the windowless PHP subsystem.

Also use `php-win.exe` (not `php.exe`) — the `-win` suffix is the windowless PHP variant.

### Linux / Mac — cron

```bash
crontab -e
# Add this line:
* * * * * cd /path/to/ThrowItBack && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Verify everything works

```bash
# Services
mysqladmin ping -h 127.0.0.1 -u root   # → "mysqld is alive"
redis-cli ping                         # → "PONG"
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/   # → 200

# Browsershot rendering
php artisan tinker
>>> \Spatie\Browsershot\Browsershot::url('https://example.com')
        ->setNodeBinary(config('archive.renderer.node_binary'))
        ->setNpmBinary(config('archive.renderer.npm_binary'))
        ->bodyHtml();
# Should return ~500 chars of HTML, no errors
>>> exit
```

If Browsershot fails, double-check `ARCHIVE_NODE_BINARY` / `ARCHIVE_NPM_BINARY` paths in `.env`.

---

## 7. Add your first site to crawl

1. Open http://127.0.0.1:8000/admin/sites
2. Click **New site**
3. Fill in:
   - Site name (e.g. "My WordPress site")
   - Base URL (e.g. `https://example.com`)
   - Crawl depth (2 is a good default)
   - Max pages (500 is generous)
   - Frequency (Daily is most common; Specific days lets you pick weekdays)
   - Storage retention (defaults to "Use default" = 3 months; "Keep forever" for important clients)
4. Save → click **Crawl now** to trigger a first crawl immediately

---

## What runs automatically

Once the scheduler is registered:

| When | What |
|---|---|
| Every minute | `crawl:dispatch-due` checks if any site's `next_run_at` has passed → spawns a detached `crawl:run` per due site |
| Nightly at 03:00 | `archive:retention` soft-deletes crawls past their retention cutoff (per site) |
| Nightly at 03:30 | `archive:trash-purge` hard-deletes runs in trash >7 days, frees disk space via the dedup pool's reference counting |

You can change cleanup time in **Admin → Settings**.

---

## Migrating existing data from another device

If you want to bring over the archive history from another machine:

```bash
# On the OLD machine — dump database + storage
mysqldump -u root site_archive > sa-backup.sql
tar -czf sa-storage.tar.gz storage/app/snapshots/

# Copy sa-backup.sql + sa-storage.tar.gz to the new machine

# On the NEW machine — restore
mysql -u root site_archive < sa-backup.sql
tar -xzf sa-storage.tar.gz -C /path/to/ThrowItBack/
```

The dedup pool layout (`_pool/{ab}/{cd}/{hash}.{ext}`) is platform-agnostic so the storage transfers cleanly between Windows / Linux / Mac.

---

## Common issues

| Symptom | Fix |
|---|---|
| `composer install` fails on Windows with "ext-pcntl missing" | Add `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` (these are Linux-only extensions, app works without them) |
| Browsershot fails with "Could not find Chromium" | Run `npm install puppeteer` again — the post-install script downloads Chromium (~250 MB) |
| `php artisan migrate` errors with "Specified key was too long" | MySQL 5.7 issue — add `Schema::defaultStringLength(191);` to `app/Providers/AppServiceProvider.php` boot. Or upgrade to MySQL 8. |
| Admin login redirects in a loop | Run `php artisan key:generate` if you skipped it. Then clear caches: `php artisan config:clear && php artisan cache:clear` |
| Crawls silently not firing | Check the Windows Task Scheduler entry exists + is enabled. On Linux: `tail -f storage/logs/laravel.log` while the cron should be firing. |
| Cmd window flashes every minute on Windows | Task Scheduler is using `InteractiveToken` instead of `S4U` logon. Re-create the task per the PowerShell snippet above. |

---

## Production hardening (when going beyond local dev)

If you ever expose this to the internet:

- Set `APP_ENV=production`, `APP_DEBUG=false`
- Set `APP_URL=https://your-domain` (the app respects `X-Forwarded-Proto` if behind a proxy)
- Set `SESSION_SECURE_COOKIE=true`
- Run behind nginx/Caddy with TLS
- See the full pre-launch checklist in [`CLAUDE.md`](./CLAUDE.md) under "Pre-launch security checklist"

---

## Folder layout cheat sheet

```
ThrowItBack/
├── app/
│   ├── Console/Commands/   ← crawl:run, crawl:dispatch-due, archive:retention, archive:trash-purge
│   ├── Filament/           ← admin panel resources, pages, widgets
│   ├── Http/               ← controllers + middleware
│   ├── Livewire/           ← user-side viewer pages (home, browse, viewer, compare)
│   ├── Models/             ← Site, CrawlRun, Snapshot, Asset, AssetFile (dedup pool), Setting, User
│   └── Services/Archive/   ← AssetDownloader, HtmlRewriter, PageRenderer (Browsershot), ArchiveCrawlObserver
├── config/archive.php      ← all crawler + retention + Browsershot tunables
├── database/migrations/
├── resources/views/        ← Blade templates
├── routes/console.php      ← scheduler entries
├── storage/app/snapshots/  ← captured archive content (deduped pool + per-snapshot HTML)
├── .env                    ← your local config (NOT in git)
└── CLAUDE.md               ← architecture handoff doc — read this for the "why"
```
