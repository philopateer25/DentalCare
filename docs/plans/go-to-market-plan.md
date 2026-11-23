# DOXamigo — Go-to-Market & Remote Control Plan

*Draft v2 — 2026-11-21. Edit freely; nothing here is built yet.*

This plan turns the conversation about "why clinics will reject us" into concrete work. Each phase has a goal, the tasks (with the files involved), and a "done when" check.

---

## Phase 0 — Decisions

### Answered
| # | Decision | Answer |
|---|---|---|
| D1 | Central server hosting / domain | Vercel app, free to start; custom domain added later |
| D2 | Central server code | **Separate repo** (`doxamigo-central`) |
| D3 | Hardware | **No clinic box** — we run on the clinic's own computers |
| D4 | Database at the clinic | **SQLite** |
| D5 | Pricing currency | EGP — already done |
| D6 | WhatsApp in the cheapest plan | **No** — plans stay as they are |
| D7 | Central app stack | **Next.js** |
| D8 | Central app hosting | **Cloudflare** (free tier allows commercial use) |
| D10 | Central app users | You + 2 others → team roles needed from day one |

**Status:** Phase 1 built on `feat/ui-redesign` (2026-11-21). Contract for Phase 2: `docs/licensing-protocol.md`.

### Still open
| # | Question | My suggestion |
|---|---|---|
| D7 | Central app stack | **Next.js (React + TypeScript) on Vercel + free Postgres (Neon or Supabase)**. Vercel runs Next.js natively; Laravel/Filament on Vercel only works through an unofficial PHP runtime and is fragile. You already write React 19 in the chairside app, so it's familiar. |
| D8 | Vercel plan | **Note:** Vercel's free Hobby plan is for *personal, non-commercial* use. Fine while building and testing; before the first paying clinic, move to Vercel Pro (~$20/mo) or to Cloudflare (free tier allows commercial use). |
| D9 | Domain timing | Buy and connect the domain **before the first real clinic is installed** — or use the "server address can be changed remotely" feature in Phase 1 so it doesn't matter. |
| D10 | Who uses the central app | Just you, or you + partner + a support person? (Decides whether we need team roles on day one.) |

---

## Phase 1 — Secure remote control (in this repo)

**Goal:** the clinic's local system obeys settings that only you can issue. Patient data never leaves the clinic.

**What exists:** `LicenseManager` sends a heartbeat every 4h (`routes/console.php`), handles `active / suspended / revoked`, keeps a 72h offline grace period with a tamper-proof clock; `CheckLicenseStatus` middleware blocks requests when locked. The server is a mock (`CentralLicenseMockController`) that always says "active".

**Gaps found:**
- The heartbeat reply is **not verified** — a fake server returning "active" would unlock any clinic.
- Feature flags live in the clinic's own database (`practices.features`), which anyone with the SQLite file can edit. This matters more now that we run on the clinic's own PC.
- The heartbeat doesn't say which clinic it is.
- Lockout is all-or-nothing (`abort(403)`) — no warning, no read-only, no data export.

**Tasks:**
1. **Signed license payload.** The central app signs every reply with an Ed25519 private key; this app ships only the public key and rejects anything that fails verification (built into PHP via libsodium).
2. **Payload contents:** `status`, `package_tier`, `features[]`, `rollout_stage`, `chair_limit`, `xray_limit`, `paid_until`, `server_time`, `clinic_id`, `server_url`, `commands[]`, `announcement`. Stored in the existing encrypted license file.
3. **`FeatureManager` reads the signed license first**; `practices.features` is used only in `APP_ENV=local`. Clinic admins can switch *off* features they have, never switch on ones they don't.
4. **Heartbeat sends:** clinic ID, app version, PC health (disk free, RAM, Windows version), last backup age, DB size, and **anonymous usage counts** (appointments/invoices/patients created today, active users). No names, phones or clinical data.
5. **Three-step lock:**
   - *Warning* — banner "subscription ends in N days" (from `paid_until`).
   - *Read-only* — view/search and **export**, no create/edit.
   - *Locked* — only the license page and data export.
   - Suspension applies **at next login / start of day, never mid-session**.
6. **"Sync now" button** on the clinic's license page; heartbeat interval from 4h → 30 min.
7. **Data export** (patients, appointments, invoices, payments → Excel/CSV + files zip), always available, even when locked.
8. **Remote server address:** the signed payload can carry a new `server_url`, so moving from `*.vercel.app` to the real domain needs no visit to any clinic.
9. **Emergency offline unlock code:** if a clinic's internet is down past 72h, you generate a short signed code in the central app (valid e.g. 48h, bound to that clinic) and read it to them over the phone.
10. **Remote commands** (from an allow-list only — never arbitrary shell): `sync`, `backup_now`, `clear_cache`, `send_logs`, `update_to_version`. Delivered in the heartbeat; result reported on the next one.
11. Keep the local bypass (`APP_ENV=local`) exactly as it is.

**Tests:** forged signature rejected; expired `paid_until` → read-only; revoked → locked; feature outside license can't be enabled; export works while locked; unlock code works only for its clinic and expires; unknown command ignored.

**Done when:** a clinic pointed at a fake server stays locked, and a feature changed in the central app appears in the clinic after "Sync now".

---

## Phase 2 — The central app (`doxamigo-central`, new repo)

**Goal:** one app — on your laptop or phone — that controls every clinic: creating them, choosing features, billing, health, support and updates. It replaces and extends the current `/system` panel (which today has only Practices — name, type, currency, plan, features, limits, license — plus Users and Support Tickets).

**Stack (D7/D8):** Next.js + TypeScript on Cloudflare Workers (OpenNext), Cloudflare D1 (SQLite), Tailwind, team login for 3 people. Signing key stored as a Cloudflare secret. Repo: `doxamigo-central` (sibling folder).

**Status (2026-11-21):** first slice built — 2.1 (except setup pull on enrollment and transfer limit), 2.2 payments + renewals + revenue, 2.3 (except daily summary cron), 2.5, 2.8 announcements, 2.10. See the central repo README.

### 2.1 Clinics — create and configure everything
- **New clinic wizard:** clinic name, owner name + phone, city, type, currency, plan, chair/x-ray limits, branches and chairs, feature picks (on top of the plan), starting rollout stage, trial length, paid-until.
- Generates a **one-time enrollment code**. On install the clinic enters it, and the local app **pulls the setup** (practice, branches, operatories, first admin account with a temporary password shown once). No more creating practices by hand on each PC.
- **Clinic page:** everything editable later — plan, features, limits, stage, paid-until, status (trial / active / warning / read-only / suspended / revoked).
- **Plans catalog managed centrally** (amigo / pro / ultra / custom): name, EGP price monthly/yearly, limits, features. Today these are hard-coded in `FeatureManager::PACKAGES`; the license payload carries the result, so changing a plan doesn't need an app update.
- **Trials:** e.g. 14 days with everything on, then drop to the chosen plan.
- **PC change / license transfer:** release the old hardware fingerprint and re-bind to the new PC (with a limit, e.g. 2 transfers per year).

### 2.2 Subscriptions & money from clinics
- Record payments you receive (InstaPay / cash / bank) → automatically extends `paid_until`.
- Monthly / yearly cycle, invoice/receipt PDF for the clinic.
- **Renewal reminders:** list of clinics due in 7 days with a one-click WhatsApp Web link to the owner (same pattern as the clinic app, pre-filled template).
- Revenue view: MRR, clinics per plan, overdue list.

### 2.3 Fleet health (all clinics at a glance)
- Table with **last seen**, app version, disk free, last backup age, DB size, PC info, current status.
- Red flags: offline > 24h, backup older than 2 days, disk < 10 GB, old app version, license about to expire.
- Daily summary of problems (Vercel cron — Hobby allows once a day) by email/Telegram to you.
- Heartbeat history per clinic.

### 2.4 Adoption & rollout
- Per clinic: rollout stage + the **usage counts** from Phase 1 (bookings/day, invoices/day, active users, which modules are used).
- **"Ready for next stage"** hint: e.g. stage 1 clinic booking ≥ 80% of visits in the system for 5 days.
- **Churn warning:** usage dropping week over week → call them before they quit.
- Onboarding checklist per clinic (installed, staff trained, debtors imported, WhatsApp templates set, stage 2 visit done…).

### 2.5 Remote actions
- Buttons per clinic: Sync now, Run backup now, Clear cache, Collect logs, Update to version X, Generate emergency unlock code.
- All actions go through the signed heartbeat (Phase 1, task 10); status shows pending → done/failed.

### 2.6 Releases & updates
- Upload a release (version, notes, signed zip). Channels: **pilot** (1–2 clinics) → **stable** (everyone).
- Clinics download and install after closing time; failures reported back; one-click rollback.
- Version spread chart so you know who's still on old versions.

### 2.7 Support
- **Support tickets move here:** clinics submit from inside the local app (with app version + recent logs attached automatically); you reply from the central app; reply appears in the clinic's app.
- Notes per clinic (calls, visits, complaints).
- Remote desktop link per clinic (RustDesk/AnyDesk ID saved on the clinic page).

### 2.8 Content pushed to clinics
- **Announcements:** banner/message to all clinics or one ("new feature", "maintenance tonight").
- **Default libraries:** WhatsApp templates, procedure catalog, default dropdown lists (Phase 5) — new clinics start with good defaults; existing clinics can choose to receive updates.

### 2.9 Sales pipeline (small CRM)
- Leads: clinic name, contact, city, source, stage (contacted → demo → trial → paying → lost) and reason lost. Useful right now with one slow clinic and new ones coming.
- Referral codes: an existing clinic refers a colleague → one free month.

### 2.10 Team & safety
- Team accounts with roles (owner / sales / support) if D10 says more than one person.
- **Audit log** of every action in the central app (who suspended which clinic, when).
- No patient data ever stored centrally — stated in `SERVICE_AGREEMENT.md`.

**Build order inside Phase 2:** 2.1 + 2.3 + 2.5 first (needed to install and control the pilot) → 2.2 → 2.4 → 2.7 → 2.6 → 2.8 → 2.9 → 2.10.

**Done when:** you can create a clinic in the central app, install the clinic PC with just the enrollment code, see it check in, change its features/stage, and suspend/unsuspend it — all from your phone.

---

## Phase 3 — Phased rollout per clinic

**Goal:** turn modules on week by week so no clinic is overloaded on day one.

| Stage | Turned on | Who it wins | Success sign |
|---|---|---|---|
| 1 | Calendar, patient quick-add, WhatsApp reminder buttons (if in plan) | Receptionist | Books in the system unprompted by Friday |
| 2 | Invoices, payments, installments, daily cash close, opening balances | Owner | Owner opens the end-of-day report themselves |
| 3 | Charting, treatment plans, prescriptions, follow-up requests | Doctor (start with one) | Procedure added in < 15 s |
| 4 | Inventory, labs, expenses, payroll, 3D, AI | Everyone | On request |

**Tasks:**
1. `rollout_stage` comes from the signed license. `FeatureManager::isEnabled()` = in plan **AND** unlocked by stage.
2. Hide Filament navigation, widgets and Inertia chairside links for modules not yet unlocked (`canAccess()` / `shouldRegisterNavigation()`).
3. "Coming next week" placeholder instead of a missing menu.
4. **End-of-day owner report:** collected today by method, outstanding balances, installments due, per-doctor commission (check existing finance widgets first).
5. Stage set from the central app (2.4).

**Status (2026-11-22):** built — stage map in `config/license.php` (`rollout_stages`), `LicenseManager::stageAllows()`, gating through `Practice::hasFeature()` plus a `clinical` module (chart, treatment plans, prescriptions, follow-ups), *Coming next* page, *Today's close* report (`DailyCloseReport`). Expenses unlock with finance at stage 2 (the cash close needs them). Not yet: "ready for next stage" hint in the central app (2.4).

**Done when:** a stage-1 clinic sees only calendar + patients + WhatsApp; moving it to stage 2 in the central app reveals finance after sync.

---

## Phase 4 — Switching from paper ("start from today, keep your notebooks")

**Goal:** answer "what about my old data?" without asking anyone to type years of records.

**Tasks:**
1. **Patient + opening-balance import** (Filament Importer, Excel/CSV): name, phone, gender/age, amount owed, optional installment schedule. Creates patients, an opening-balance invoice and an `InstallmentPlan`. Arabic + English Excel template, dry-run preview with row errors, tenant-scoped.
2. **"Scan paper card" button** on the patient page: shows a QR code → short-lived single-use upload page on the receptionist's phone (same Wi-Fi, signed URL, ~10 min) → photos land in that patient's files (`PatientFile`, protected disk, category "Paper record").
3. **Optional AI read of the card** (ultra plan): suggest name, phone, balance from the photo for confirmation — never auto-save. New class (e.g. `PaperCardExtractor`), **no changes to `GeminiService.php`**.
4. **"Returning paper patient" flow:** search → not found → "Create from paper card" (name + phone + photo) in ~30 s.
5. Setup-day service: we enter everyone who currently owes money (Phase 7 playbook).

**Status (2026-11-22):** built — 1 (custom "Import patients" page instead of the Filament Importer, so the check-then-import preview runs without the queue), 2 (one-time token instead of a signed URL; `CLINIC_LAN_URL` for the phone address; QR shown when `bacon/bacon-qr-code` is installed, otherwise the link), 4. Not yet: 3 (AI read of the card).

**Done when:** a 200-row debtors Excel imports with errors flagged, and a phone photo lands on the right patient in under 30 s.

---

## Phase 5 — "I don't have time to type": dropdowns you can add to

**Current state:** 139 `Select::make` fields in `app/Filament`, only 3 with `createOptionForm`.

**Tasks:**
1. **Audit all 139 selects** into *fixed* (doctor, patient, status, tooth, branch) vs *creatable* (diagnosis, complaint, medication, allergy, condition, procedure note, expense category, supplier, lab item…). Table delivered for your review before any change.
2. **One reusable component** (`CreatableSelect::make()`): search → "Add '…'" → saved to the clinic's own list (`practice_id`).
3. **Per-clinic option lists** (generic `practice_options` table by list type, or existing tables like `ProcedureCode`). Default lists can come from the central app (2.8).
4. **Most-used first:** order by this doctor's/clinic's usage in the last 90 days.
5. Same pattern in the Inertia chairside screens (React combobox).
6. **Timing check** of the 5 core flows (book, check in, add procedure, take payment, send WhatsApp) before/after.

**Status (2026-11-23):** tasks 2–4 built — `practice_options` table, `CreatableSelect::make()/tags()/phrases()`, Settings → Dropdown lists, recent-then-most-used order (per clinic). Applied to prescriptions, procedure tools/materials/note phrases, medical history tags and expense category. Task 1 audit for review: `docs/plans/phase5-dropdown-audit.md`. Not yet: chairside React combobox (5), per-doctor ordering, timing check (6).

**Done when:** a doctor records a common procedure + note without the keyboard, and a medication typed once appears in the list next time.

---

## Phase 6 — Running on the clinic's own computer (no Docker)

**Goal:** install on ordinary — including old — Windows PCs. Today's `deploy/install.ps1` needs Docker, which needs hardware virtualization; many clinic PCs don't have it. Because we chose SQLite, we can drop Docker entirely.

**Tasks:**
1. **Native Windows package** (new `deploy/windows/`): portable PHP 8.3 with the needed extensions, a web server (Caddy or nginx + several `php-cgi` workers — `php artisan serve` is single-user and not suitable), the app with built frontend assets, SQLite file under `C:\DOXamigo\data`.
2. **Windows services** (via WinSW or NSSM): web server, PHP workers, queue worker. Task Scheduler runs `php artisan schedule:run` every minute (heartbeat, backups, license tick).
3. **Installer** (Inno Setup) → one `DOXamigo-Setup.exe`: checks the PC, installs services, opens the firewall port for the local network, asks for the **enrollment code** (Phase 2.1), creates desktop shortcut, prints the address other devices should open (e.g. `http://192.168.1.10`).
4. **Minimum PC check** in the installer: Windows 10+ 64-bit, ≥ 4 GB RAM, ≥ 20 GB free disk; warns instead of failing.
5. **Other devices** (reception PC, doctor tablet, phones) just open the browser to the main PC. Main PC must stay on during clinic hours — sleep disabled by the installer.
6. **Updates:** central app (2.6) tells the clinic a new version exists; a local updater downloads the signed zip, backs up, swaps files, runs migrations, restarts services, rolls back on failure.
7. **Backups:** keep the encrypted nightly backup (`backup:run` 02:00); add copy to a USB drive and/or the clinic's Google Drive desktop folder (reuse `scripts/clinic-backup-task.ps1`). Backup age reported in the heartbeat.
8. **Power cuts:** recommend a small UPS for the main PC in the playbook; SQLite WAL already handles sudden stops reasonably; the updater never runs during clinic hours.
9. **Remote support:** RustDesk (free, self-hostable) installed by the installer, ID saved to the central app.
10. **Old browsers / weak PCs:** 3D odontogram falls back to 2D without WebGL or on low-end devices; test main screens on an old Windows PC and a cheap Android tablet.
11. **Hardware fingerprint** (`HardwareFingerprint`) verified to be stable on Windows across reboots and Windows updates.
12. Docker install stays available for developers / capable PCs, not as the clinic default.

**Done when:** `DOXamigo-Setup.exe` installs on an old Windows 10 PC without virtualization, the reception PC and a phone can open it on the same Wi-Fi, and it survives a reboot and an update.

---

## Phase 7 — Business side

1. **Price check vs Dentalize:** if their 20–26k is EGP/year, our plans need to be compared in EGP/year too. Also, `FeatureManager::PACKAGES` labels still show `$40/mo` etc.; move to EGP labels or, better, read them from the central plans catalog (2.1).
2. **Onboarding playbook** (`docs/onboarding-playbook.md`):
   - Setup day: install, staff accounts, debtors import, WhatsApp templates, UPS advice.
   - Week-by-week stages (Phase 3) with success signs.
   - Be on-site or on video at opening on day 1 of each stage.
   - Answers to objections: old data, "what if you disappear", "my receptionist won't learn it", "what if it breaks", internet/power cuts.
3. **Pilot:** 3–5 clinics including the current slow one — offer "try appointments only for one week" as a smaller first yes. Track them in the central CRM (2.9).
4. Keep ETA e-invoicing off and out of marketing.

---

## Suggested order

```
Phase 1 (signed license, lock steps, export)  ──►  Phase 2 core (2.1, 2.3, 2.5)  ──►  Phase 3 (stages)
                                                        │
Phase 6 (Windows installer) ◄── needs enrollment code ──┘
Phase 5 (creatable dropdowns)   ← independent, can start any time
Phase 4 (import + scan card)    ← independent
Phase 2 rest (2.2, 2.4, 2.6–2.10) after the pilot is running
Phase 7 alongside everything
```

Phase 1 and Phase 2 core must be built together — they're the two ends of the same signed heartbeat.

## Out of scope for now
- Egyptian Tax Authority e-invoicing.
- Clinic hardware box.
- Cloud sync of patient data.
