# Deployment and operations

There are three supported deployment paths in the repo:

1. **Local Docker** (`docker-compose.yml`) — development stack with bind-mounted source.
2. **Production compose** (`docker-compose.prod.yml`) — self-hosted clinic server on a LAN.
3. **Windows on-prem installer** (`deploy/install.ps1`) — one-click Docker Desktop + Tailscale install for a clinic PC.

None of them is production-hardened as written. Read the gaps before going live.

## 1. Local Docker

```bash
cp .env.example .env
php artisan key:generate            # or set APP_KEY manually
docker compose up -d --build
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

Services in `docker-compose.yml`:

| Service | Image/build | Port | Notes |
| --- | --- | --- | --- |
| `nginx` | built `web-server` stage | 80, 443 | Serves `public/`; no TLS config, so 443 is non-functional |
| `app` | built `production` stage | 9000 (internal) | Bind-mounts `.:/var/www/html`, so the built code is replaced by the host tree |
| `worker` | same image | — | `queue:work` |
| `scheduler` | same image | — | `schedule:work` |
| `db` | `mysql:8.0` | internal | Credentials from env with `secret` fallbacks |
| `redis` | `redis:7-alpine` | internal | Cache/queue in some configs |

Because `app` bind-mounts the repo over the image and the repo may not have `vendor/`, the stack uses a named `clinic_vendor` volume. That means the first `composer install` happens on the host, not in the container, unless you run it inside. If `vendor/` is missing, exec into `app` and `composer install`.

The source bind mount also means the built frontend assets in the image are masked by the host `public/build` (or its absence). Run `npm run build` on the host or `docker compose exec app npm run build`.

## 2. Production compose

```bash
# On the server
git clone <repo> /opt/dentalcare && cd /opt/dentalcare
cp .env.example .env
# Edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://..., DB_*, APP_KEY
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan storage:link
```

What it does:

- `nginx` publishes 80 and 443.
- `app`, `worker`, `scheduler` run the same image with production env.
- MySQL and Redis are internal only.
- `storage/app` is a named volume shared by app/worker/scheduler.
- The entrypoint waits for the DB, runs `php artisan migrate --force`, then starts PHP-FPM.

### Known production issues

| Issue | Impact | Fix |
| --- | --- | --- |
| Nginx listens on **80 only** while compose publishes 443 | HTTPS does not work; clinic traffic is plaintext on the LAN | Add a TLS server block and certificate (internal CA or Let's Encrypt with DNS-01), redirect 80 → 443 |
| `Dockerfile` installs Node/npm into the runtime image and uses `npm install` | Large attack surface, non-reproducible builds | Build assets in a separate stage with `npm ci`, copy only `public/build`, keep Node out of the final image |
| Entrypoint runs migrations on every container start | Concurrent migrations if app/worker/scheduler all start; slow boots | Run migrations as a one-shot job/command, not in the entrypoint; or gate on the app service only |
| No healthchecks | Orchestrator cannot detect a dead app/db | Add `healthcheck` for `/up`, MySQL, Redis |
| Default `DB_PASSWORD=secret`, `MYSQL_ROOT_PASSWORD=root_secret` | Weak credentials if env is incomplete | Require explicit secrets; fail fast when unset |
| No queue failure handling | Failed jobs accumulate | Add `queue:retry`, `failed_jobs` monitoring, and a supervisor/restart policy |
| Storage volume only covers `storage/app` | Logs, cache, sessions live in the container and vanish on recreate | Mount `storage/` and `bootstrap/cache`, or use Redis/log driver |
| No backup job | Data loss on volume corruption | See §7 |
| Assets baked into the image but `public/` not volume-mounted | Patient uploads to `public/` are lost | Store uploads on the `local`/S3 disk under `storage/app`, not `public/` |
| `config:cache` not run | Slower boot | Run `php artisan optimize` in the entrypoint, but first fix runtime `env()` calls |

### Recommended production topology

```
Internet/LAN
   │
   ├── reverse proxy (Caddy/Traefik/nginx) ── TLS, HSTS, rate limit
   │        └── app (php-fpm)
   ├── worker (queue:work --tries=3 --timeout=120)
   ├── scheduler (schedule:work)
   ├── MySQL 8 (persistent volume, daily dump)
   └── Redis 7 (persistent, AOF)
```

If the clinic only needs LAN access, put the server behind a VPN (Tailscale is already used by the installer) and do not expose ports to the internet.

## 3. Windows on-prem installer

`deploy/install.ps1` is intended to be run **as Administrator** on a Windows clinic PC. It:

1. Checks CPU virtualization.
2. Creates the install and storage directories.
3. Installs Docker Desktop silently (optionally to a non-C drive).
4. Installs Tailscale and joins the machine to a tailnet.
5. Asks for a clinic enrollment code and writes `storage/app/license.json`.
6. Generates an `APP_KEY`.
7. Writes `docker-compose.yml` and `nginx.conf`, pulls `philohany12/dentalcare-app:latest`, starts nginx/app/db/redis.
8. Runs `php artisan db:seed --force` once.
9. Creates a desktop shortcut to `http://localhost/admin`.

### Installer problems (fix before distribution)

1. **Hardcoded Tailscale auth key.** The script embeds a real `tskey-auth-…` key. Rotate it immediately and pass a scoped, single-use key via parameter or a secrets store. Never commit it.
2. **Hardcoded database passwords** (`secret`, `root_secret`) in the generated compose.
3. **Incompatible license artifact.** The installer writes `license.json`, but `LicenseManager` reads the AES-encrypted `storage/app/license.enc`. The generated file is ignored.
4. **No activation path.** Even with a correct file, nothing calls `LicenseManager::activate()`. The app will be `inactive` and `/admin` will 403.
5. **No worker or scheduler** in the generated compose. Queues (WhatsApp, notifications) and `license:tick`/`license:heartbeat` never run.
6. **No TLS.** The shortcut uses `http://localhost/admin`; Tailscale access is also plaintext unless you add HTTPS.
7. **Deprecated `docker-compose` v1 binary** is assumed; modern Docker uses `docker compose`.
8. **Seeding on every install.** If the script is re-run against an existing volume, `db:seed` can duplicate data or exhaust memory. It only "waits" for a `DONE` string.
9. **No image pinning or signature verification.** `:latest` is pulled from Docker Hub; a compromised tag is a supply-chain risk.
10. **Secrets in process arguments.** `tailscale up --authkey=…` is visible in process lists and logs.
11. **No uninstall/upgrade path.** The script force-removes containers and `down -v` (which deletes volumes) on every run.
12. **`deploy/DentalCare.exe`** is a 34 KB binary in the repo. Its provenance is undocumented; do not distribute it without review.

## 4. Scheduler and queues

Required for the product to behave correctly:

| Process | Command | Why |
| --- | --- | --- |
| Queue worker | `php artisan queue:work --sleep=3 --tries=3 --timeout=120` | WhatsApp sends, notifications, post-op jobs |
| Scheduler | `php artisan schedule:work` (or cron `* * * * * php artisan schedule:run`) | `license:tick` every 10 min, `license:heartbeat` every 4 h |

Cron alternative:

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor example:

```ini
[program:dentalcare-worker]
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/dentalcare-worker.log

[program:dentalcare-scheduler]
command=php /var/www/html/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/dentalcare-scheduler.log
```

Queue connection is `database` locally and `redis` in production compose. If the worker is down, messages and notifications silently queue up (or fail when `jobs` fills). Monitor `failed_jobs`.

## 5. License activation

Current state:

- `LicenseManager` stores state in `storage/app/license.enc` (AES-256-GCM, key derived from `APP_KEY`).
- `TamperProofClock` stores elapsed time in `storage/app/license_clock.enc` and `license_clock_ticks`.
- `CheckLicenseStatus` is attached to `/admin` tenant middleware. If `isLockedOut()` is true, it returns 403.
- Local/testing always report active.
- **No UI, route, or artisan command calls `activate()`.** A production deployment therefore starts `inactive` and the admin panel is unusable.

What you need to add:

1. An activation screen (Filament page or install command) that accepts a license key and calls `LicenseManager::activate($key)`.
2. A `license:activate {key}` command for headless installs.
3. A `license:status` command for support.
4. A decision on whether licensing is per-installation (current file) or per-practice (`practices.license_key`), then remove the unused representation.
5. A migration path from the installer's `license.json` (or delete that path entirely).

Do not rely on `APP_ENV=production` + a missing file "failing closed" as a security control until activation exists; operators will simply set `APP_ENV=local`.

## 6. TLS and network

- Terminate TLS at a reverse proxy. Do not publish PHP-FPM or MySQL.
- Set `APP_URL=https://clinic.example.com`; `AppServiceProvider` forces HTTPS scheme when `APP_URL` starts with `https://`.
- Enable HSTS at the proxy, not in Laravel.
- Restrict `/system` to the vendor network/VPN. It has no license middleware and exposes all practices.
- Restrict `/api/internal/license/*` and `/api/whatsapp/webhook` at the proxy or remove them in production.
- If using Tailscale, prefer `tailscale serve`/`funnel` with TLS instead of exposing port 80.
- Keep `public/models` (263 MB) out of the web root if you serve it from object storage; otherwise cache it aggressively.

## 7. Backups

Back up these four things:

| Item | Location | Method |
| --- | --- | --- |
| MySQL | `clinic_db_data` volume | `mysqldump --single-transaction --routines --triggers` daily, retain 30 days, encrypt off-site |
| Uploaded/clinical files | `storage/app` volume (`private`, `public`, `voice_notes`) | Filesystem snapshot or `restic`/`rclone` |
| License/clock state | `storage/app/license.enc`, `license_clock.enc` | Include in the storage backup |
| App config/secrets | `.env`, proxy config | Secret manager, not git |

Restore drill:

```bash
docker compose -f docker-compose.prod.yml down
# restore DB dump into a fresh mysql volume
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

Test a restore quarterly. A backup you have never restored is a hypothesis.

### Data retention

There are no soft deletes. Deleting a patient cascades through clinical and financial records. Take a dump before any bulk cleanup and consider adding soft deletes/audit logging before storing real patient data (see [SECURITY §6](SECURITY.md#6-data-protection-and-privacy)).

## 8. Go-live checklist

**Security**

- [ ] `/quick-login` removed or restricted to local env.
- [ ] Fallback auto-login in `PatientOdontogramController` removed.
- [ ] `auth` + tenant checks on all clinical API routes.
- [ ] Webhook signature/shared secret validated; CSRF exemption narrowed to the webhook path only.
- [ ] Public license mock routes removed/disabled.
- [ ] All seed/demo accounts removed or passwords rotated; no `password` credentials.
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, unique `APP_KEY`.
- [ ] `/system` restricted to VPN/vendor IPs.
- [ ] TLS enabled, HTTP redirected, HSTS on.
- [ ] Secrets (Tailscale key, DB passwords, Evolution/Gemini/VAPID) injected from a secret store, not committed.
- [ ] Rate limiting on login, webhook, and clinical writes.

**Correctness**

- [ ] `patients.branch_id` added or branch analytics rewritten.
- [ ] License activation implemented and tested; grace/lockout verified.
- [ ] Queue worker and scheduler running under supervision.
- [ ] `config:cache`/`route:cache`/`view:cache` succeed (requires moving runtime `env()` calls into config).
- [ ] WhatsApp webhook URL configured to the real host; `WhatsAppService` either implemented or callers switched to the queue jobs.
- [ ] `practice_id` resolution in the webhook does not default to `1`.
- [ ] Invoice/patient number generation is safe under concurrent writes.

**Operations**

- [ ] Database and storage backups scheduled and a restore tested.
- [ ] Log aggregation and alerting (at minimum `failed_jobs`, 5xx, disk).
- [ ] Uptime check on `/up`.
- [ ] Disk capacity plan for `public/models` + uploads + MySQL.
- [ ] Upgrade procedure documented (pull image, backup, migrate, rollback).
- [ ] Demo seeders disabled in production (`db:seed` never run automatically).

**Product**

- [ ] Onboarding tested end-to-end for a new practice.
- [ ] Each feature flag's default and system-only behavior verified.
- [ ] Role matrix verified with `doctor`, `secretary`, `clinic_admin`, `super_admin`.
- [ ] Patient data export/print and prescription printing verified.
- [ ] Arabic/French locale rendering and RTL checked.
