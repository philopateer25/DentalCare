# DOXamigo — native Windows install (Phase 6)

One `DOXamigo-Setup.exe` that installs on an ordinary Windows 10/11 64-bit PC. It needs no Docker and no virtualization.

## What gets installed

```
C:\DOXamigo\
  app\                 the application (replaced by every update)
  app\storage  ->  data\storage   (junction: uploads and backups survive updates)
  runtime\php\         PHP 8.3 (NTS x64) + php.ini
  runtime\caddy\       Caddy web server + generated Caddyfile
  runtime\winsw\       WinSW (runs programs as Windows services)
  services\            one WinSW wrapper per service
  data\                .env, database.sqlite, storage, logs   <- never touched by updates
```

**Windows services** (all set to start automatically and restart on failure):

| Service | What it does |
|---|---|
| `DOXamigo-Web` | Caddy on port 80, or 8080 if 80 is taken. Serves the app to this PC and the clinic network. |
| `DOXamigo-PHP-1…4` | `php-cgi` workers. Caddy spreads requests across them, so several users work at once. |
| `DOXamigo-Queue` | Background jobs (`queue:work database`). |
| `DOXamigo-Scheduler` | `schedule:work`: license heartbeat every 30 min, license clock, backup at 02:00. |

**`setup.ps1`** does the following. It's run by the installer, and running it again is safe (an update runs it too):
1. Checks PHP and its extensions, and installs the Visual C++ runtime if PHP needs it.
2. Creates `data\` and the storage junction.
3. Writes `.env` on first install. It gets a random `APP_KEY`, the DB path, the PC's address on the clinic network (`CLINIC_LAN_URL`), and the central app URL.
4. Runs `migrate`, then `clinic:install`: one clinic plus one admin with a **random** password. No demo data and no `password` accounts.
5. Caches config, routes and views, and installs and starts the services.
6. Opens the firewall port, for **private/domain networks only**.
7. Keeps the PC awake while it's plugged in.
8. Activates the license with the enrollment code (`license:activate`).
9. Creates a desktop shortcut and shows the addresses the other devices should open.

The installer warns, but doesn't block, if the PC has under 4 GB RAM, under 20 GB free, or Windows older than 10.

## Building the .exe

On a developer PC with git, composer, node, PHP 8.3+ and [Inno Setup 6](https://jrsoftware.org/isdl.php):

```powershell
# One time: download the runtime and record its SHA-256 in versions.json (review, then commit)
.\deploy\windows\build.ps1 -PinHashes -AllowNoProductionKey

# Every release (clean working tree; builds from HEAD)
.\deploy\windows\build.ps1 -CentralUrl https://central.doxamigo.com
```

The output is `deploy\windows\build\DOXamigo-Setup-<version>.exe`.

> **Before the first real clinic:** `config/license.php` → `public_keys` must contain the **production** public key of the deployed central app. Clinics run `APP_ENV=production`, which ignores `LICENSE_PUBLIC_KEYS` in `.env` on purpose. `build.ps1` refuses to build without that key unless you pass `-AllowNoProductionKey`, which is for test installers only.

## Testing on a spare PC or VM

Use Windows 10 64-bit with no Docker and virtualization off. The steps are in `docs/testing/manual-test-guide.md` → **Phase 6**.

## Updating a clinic

Run the newer `DOXamigo-Setup-*.exe` on the clinic PC. It:
1. Stops the services.
2. Replaces `app\` and `runtime\`. `data\` is left alone.
3. Runs `setup.ps1` again (migrations, caches, services).

Automatic updates from the central app (plan 2.6 / 6.6) are not built yet.

## Uninstalling

**Apps → DOXamigo → Uninstall** removes the services, the firewall rule and the program files. It **keeps `C:\DOXamigo\data`**, so reinstalling continues with the same database.

## Docker (developers)

Docker stays available. Set `LICENSE_MACHINE_ID` (any random string) in `.env` so the web, scheduler and worker containers count as one PC. Without it, the automatic 30-minute sync is refused (409). The variable is ignored outside a container.
