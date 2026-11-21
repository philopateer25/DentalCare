# DOXamigo Licensing Protocol (clinic app ⇄ central app)

The clinic app (this repo) and the central app (`doxamigo-central`) talk over HTTPS.
**Patient data never leaves the clinic.** Only license settings, machine health and
anonymous counts travel.

Client code: `app/Services/LicenseManager.php`, `app/Services/Licensing/*`.
Tests that pin this contract: `tests/Feature/LicensingTest.php`.

---

## 1. Keys

- The central app signs with an **Ed25519 private key** (kept in Cloudflare secrets only).
- The clinic app ships the matching **public key** in `config/license.php` → `public_keys`
  (`kid => base64`). Outside production, `LICENSE_PUBLIC_KEYS="k1:BASE64"` can add keys.
- Generate a pair: `php artisan license:keygen --kid=k1` (or any Ed25519 tool).
- Rotation: add `k2` to `public_keys`, ship an update, then start signing with `k2`.

## 2. Envelope

Every reply is (or contains, under a `license` key):

```json
{
  "kid": "k1",
  "payload": "<base64url(JSON payload)>",
  "signature": "<base64url(Ed25519 signature of the payload STRING above)>"
}
```

The signature covers the base64url string exactly as sent — no JSON canonicalisation.
In Node / Cloudflare Workers: `crypto.subtle.sign('Ed25519', key, new TextEncoder().encode(payloadB64))`.

## 3. Endpoints (served by the central app)

### `POST {server_url}/api/license/activate`
Request: `key`, `hardware_fingerprint`, `machine_name`, `app_version`, `nonce`.
Reply: envelope with `status: "active"`, or HTTP 4xx `{ "error": "..." }`.

### `POST {server_url}/api/license/heartbeat` (every 30 min + "Sync now")
Request:
| field | meaning |
|---|---|
| `clinic_id` | from the last verified payload |
| `hardware_fingerprint` | SHA-256 of machine id + MAC + hostname |
| `nonce` | random hex — **must be echoed in the payload** |
| `app_version`, `elapsed_hours`, `last_issued_at` | |
| `health` | `app_version, php_version, os, disk_free_gb, disk_total_gb, db_size_mb, last_backup_at` |
| `usage` | counts only: `patients_created_today, appointments_created_today, appointments_scheduled_today, invoices_created_today, payments_created_today, treatment_procedures_created_today, active_users_today, total_patients` |
| `command_results` | `[{id, type, ok, output, finished_at}]` for commands run since the last heartbeat |

Optional header `X-License-API-Key` when `LICENSE_API_KEY` is set.

## 4. Payload fields

| field | required | meaning |
|---|---|---|
| `nonce` | ✔ | echo of the request nonce (anti-replay) |
| `clinic_id` | ✔ | central clinic id; later replies for another clinic are rejected |
| `issued_at` | ✔ | ISO-8601 server time (resets the offline grace clock) |
| `status` | ✔ | `active` \| `suspended` \| `revoked` |
| `status_effective_at` | | when a suspension starts; default = next midnight after `issued_at` |
| `fingerprint` | | if present, must equal the clinic machine's fingerprint |
| `package_tier` | | `amigo` \| `amigo_pro` \| `amigo_ultra` \| `custom` |
| `features` | | list of feature keys (see `FeatureManager::FEATURES`). **Ceiling**: the clinic can turn these off, never turn others on. Omit = no restriction. |
| `rollout_stage` | | 1–4 (used by Phase 3) |
| `chair_limit`, `xray_limit` | | shown on the License page |
| `paid_until` | | `YYYY-MM-DD`; omit = no expiry |
| `read_only_after_days` | | default 3 |
| `lock_after_days` | | default 30 (counted from the start of read-only) |
| `unlock_secret` | | base64 32 bytes, per clinic, for emergency unlock codes |
| `server_url` | | move the clinic to a new **https** address |
| `commands` | | `[{id, type, args?}]` — see §6 |
| `announcement` | | `{id, message, level: info\|warning\|danger\|success}` banner |

## 5. Modes the clinic app derives

| Condition | Mode |
|---|---|
| no license / bad signature / revoked / tampered clock / offline > 72h (no unlock code) | **locked** |
| `suspended` and now ≥ `status_effective_at` | **read-only** |
| now ≥ `paid_until`+1 day + `read_only_after_days` | **read-only** |
| … + `lock_after_days` | **locked** |
| otherwise | **full** |

Banners: 7 days before `paid_until`, after `paid_until`, pending suspension, offline 48h+, announcement.
All billing boundaries are midnight (app timezone), so nobody is cut off mid-session.

- **Read-only**: pages open; Gate denies create/update/delete; any Eloquent save/delete in a web
  request throws HTTP 423. Users, support tickets and notifications stay writable.
- **Locked**: every page redirects to *Settings → License & Subscription*.
- **Always allowed**: login/logout, the License page, Sync now, unlock code, **Export all data** (`/license/export`).

## 6. Remote commands (allow-list only)

| type | effect |
|---|---|
| `sync` | no-op (the heartbeat already synced) |
| `backup_now` | `php artisan backup:run` |
| `clear_cache` | `cache:clear` + `view:clear` |
| `send_logs` | first line of the last 50 ERROR/CRITICAL log entries (no stack traces) |
| `update_to_version` | reports "not supported" until the Phase 6 updater exists |

Each `id` runs once; results go back in the next heartbeat's `command_results`.
Unknown types are reported as failed and never executed.

## 7. Emergency unlock code

`XXXX-XXXX-XXXX-XXXX` = base32 (RFC 4648 alphabet) of 10 bytes:
- bytes 0–3: expiry as whole hours since Unix epoch, big-endian (`ceil(ts/3600)`)
- bytes 4–9: first 6 bytes of `HMAC-SHA256(base64decode(unlock_secret), "doxamigo-unlock|{clinic_id}|{hours}")`

Valid only for that clinic, until the expiry, and at most 168 h ahead. It only lifts the
**offline** lock — never a suspension or billing lock. The next successful sync clears it.
Reference implementation: `app/Services/Licensing/UnlockCode.php`.

## 8. Known limits

- On-premise PHP code can be edited by someone with full access to the clinic PC; signing
  protects against fake servers, replayed replies and database/.env edits, not code patching.
- Writes made with the raw query builder are not covered by the read-only guard.
- `send_logs` sends error headlines only, but an exception message could still contain data.
