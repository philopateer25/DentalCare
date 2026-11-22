# Manual test guide

Click-through checks for each finished phase. Every phase adds a section here.
Time needed: about 45 minutes for Phases 1 + 2 together.

---

## 0. One-time setup (about 10 minutes)

You need two apps running at the same time:

| App | Where | URL |
|---|---|---|
| Clinic app (DentalCare) | Docker, as usual | http://localhost |
| Central app (doxamigo-central) | PowerShell on your PC | http://localhost:3000 |

### 0.1 Start the central app

```powershell
cd D:\projects\rafik\doxamigo-central
npm install
npm run db:migrate:local
npm run dev
```

`.dev.vars` already holds a signing key and a setup token. If it is missing:
run `npm run keygen`, copy `.dev.vars.example` to `.dev.vars` and paste the SECRET line.

Open http://localhost:3000. Without an account yet → **/setup**: enter the `SETUP_TOKEN` from
`.dev.vars`, your name, an email and a password (10+ characters).
If you already made the test account, its login is written at the bottom of `.dev.vars`.

### 0.2 Point the clinic app at it

Add to DentalCare `.env` (the public key is the `PUBLIC` line printed by `npm run keygen` for the key in `.dev.vars`):

```
LICENSE_ENFORCE=true
LICENSE_SERVER_URL=http://host.docker.internal:3000
LICENSE_PUBLIC_KEYS="k1:PASTE_PUBLIC_KEY_HERE"
```

To print the public key of the current `.dev.vars` key:

```powershell
cd D:\projects\rafik\doxamigo-central
node -e "const s=require('fs').readFileSync('.dev.vars','utf8').match(/LICENSE_SIGNING_KEY=(.+)/)[1].trim();console.log(Buffer.from(s,'base64').subarray(32).toString('base64'))"
```

Rebuild the clinic app's caches and reload PHP (needed after every `.env` or code change, because
Docker caches config, routes, Filament pages and PHP code):

```powershell
docker exec clinic_app sh docker/optimize.sh
```
```powershell
docker restart clinic_nginx
```

If the **Settings → License & Subscription** page is missing or shows 404, these two commands were skipped.

> `LICENSE_ENFORCE=true` turns the real license rules on even though `APP_ENV=local`.
> Remove those three lines and run `config:clear` again to go back to normal development.

---

## Phase 1 + 2 — remote control of clinics

### A. Create a clinic in the central app
1. Central → **+ New clinic**. Name "Test Clinic", your own WhatsApp number, plan **amigo**, trial **0** days, Paid until = a date 20 days from today.
2. Press **Create clinic & enrollment code**.
   - ✅ A green box shows a code like `DXA-7KQ2-MW9P-X4TB`. Copy it (it is shown only once).

### B. Install = activate the clinic
1. Clinic app → log in as admin → you are sent to **Settings → License & Subscription** (no license yet = locked).
   - ✅ Other pages redirect back to the License page.
2. **Enter license key** → paste the enrollment code → submit.
   - ✅ "License activated". Status: Active, plan amigo, licensed features = Online doctor, Labs, Insurance, Staff.
3. Central → open the clinic.
   - ✅ "PC & health" shows Last sync "just now", PC name, version.
4. Enter the same code again on the clinic → ✅ refused ("not valid") — codes work once.

### C. Features are capped by the license
1. Clinic app: Finance / Installments / WhatsApp / Inventory menus are **gone** (not in amigo).
2. As super admin, try turning Finance on for the practice in the clinic app → ✅ it stays off.
3. Central → clinic → Settings → tick **Finance & Invoicing** → Save.
4. Clinic → License page → **Sync now**.
   - ✅ Finance appears in "licensed features" and its menu comes back.
5. Central → **Plans** → amigo → tick **Inventory** → Save → clinic **Sync now** → ✅ Inventory appears (plan change reached the clinic, no update needed).
   Untick it again afterwards.

### D. Health and usage
1. In the clinic app create a patient, an appointment and an invoice.
2. **Sync now**, then central → clinic.
   - ✅ Usage shows New patients 1, Bookings made 1, Invoices 1.
   - ✅ No patient name or phone appears anywhere in the central app.
3. ✅ The "Today" page lists the clinic under *Needs attention* if there is no backup yet or disk < 10 GB.

### E. Remote actions
1. Central → clinic → **Run backup now**. ✅ History shows `pending`.
2. Clinic → **Sync now** (or wait up to 30 minutes).
3. Clinic → **Sync now** once more (results are reported on the following sync).
   - ✅ Central shows `done` with the backup output; the backup exists in the clinic's Backups page.
4. Repeat with **Collect recent errors** → ✅ output shows short error lines (or nothing), never stack traces.

### F. Announcements
1. Central → clinic → *Message to this clinic*: "Test message" → Send.
2. Clinic → **Sync now** → ✅ banner at the top of the back-office and the chairside screens.
   (Chairside banner needs `npm run build` in DentalCare once.)

### G. Billing ladder (warning → read-only → locked)
1. Central → clinic → Settings → Paid until = **today + 3 days** → Save → clinic **Sync now**.
   - ✅ Yellow banner: renewal due. Central "Today" → *Renewals* lists it with a **WhatsApp reminder** button (opens WhatsApp Web with an Arabic message).
2. Paid until = **5 days ago** → Save → Sync now.
   - ✅ **Read-only**: pages open, but saving anything (e.g. editing a patient) fails with "read-only" message. Login, License page and Export still work.
3. Paid until = **40 days ago** → Save → Sync now.
   - ✅ **Locked**: every page goes to the License page. **Export all data** still downloads a ZIP (open it: one CSV per table + patient files).
4. Central → **Record payment**: 1 month, InstaPay → ✅ Paid until moves forward → clinic Sync now → ✅ back to full.

### H. Suspend and reactivate (owner only)
1. Central → Status → **Suspend now** → a yellow "Are you sure?" bar appears under the button → **Yes, do it** → clinic Sync now → ✅ read-only + "suspended" banner.
2. **Reactivate**, then **Suspend from midnight** → **Yes, do it** → ✅ central says "Suspension starts … 00:00"; the clinic is not blocked yet.
3. **Reactivate** → Sync now → ✅ full again.

### I. Emergency unlock code (internet down > 3 days)
What it's for: the clinic PC must reach the central app at least once every 3 days. If the clinic's
internet is down longer than that, the app locks itself. The owner phones you, you read them a code,
and they type it in → the clinic works offline for 24 more hours. It does **not** lift a lock for
not paying or a suspension.

Simulating 3 days offline is hard by hand; the quick check is:
1. Central → clinic → **Generate unlock code** (24 hours) → ✅ code `XXXX-XXXX-XXXX-XXXX`.
2. Clinic → License page → **Enter unlock code** → ✅ accepted ("valid until …").
3. Enter a made-up code → ✅ rejected.
(The full 72-hour lockout is covered by the automated tests.)

### J. Moving to a new PC
1. Central → **Move to a new PC** → confirm → ✅ new code shown.
2. Clinic → **Sync now** → ✅ fails ("not the registered PC") — the old PC is cut off.
3. Enter the new code via **Enter license key** → ✅ active again. Central shows "PC moves: 1".

### K. Team and roles
1. Central → **Team** → add a teammate as **support** → copy the temporary password.
2. Sign in as them in a private window.
   - ✅ They can run remote actions and make unlock codes.
   - ✅ They do **not** see Team / Activity, cannot edit settings or suspend.
3. As owner → **Activity** → ✅ every action above is listed with who did it.
4. Wrong password 8 times → ✅ "Too many attempts" for 15 minutes.

### L. Revoke (do last)
1. Central → Status → *Revoke license…* → type the clinic name → Revoke.
2. Clinic → Sync now → ✅ license removed, clinic locked; data export still works.

## Phase 3 — modules switched on stage by stage

Stages: **1** Reception (calendar, patients, WhatsApp) · **2** Money (invoices, payments, installments,
insurance, expenses, *Today's close*) · **3** Doctors (dental chart, treatment plans, prescriptions,
follow-ups) · **4** Everything else (labs, inventory, staff, payroll, 3D, AI, online doctor).
A module shows only when the stage has reached it **and** it is in the clinic's plan.

Use a clinic on **amigo Pro** (amigo has no finance). After each stage change in the central app
press **Sync now** on the clinic's License page, then reload.

### M. Stage 1
1. Central → clinic → Rollout stage **1** → Save → clinic Sync now.
2. ✅ Sidebar: Dashboard, **Coming next**, Appointments, Patients … — no Finance, Insurance, Staff & Employees, Labs, Inventory.
3. Open a patient → ✅ no Finance & Billing, Lab Orders, Treatment Plans or Prescriptions tabs; no "Open chart" button.
4. Open **Coming next** → ✅ Stage 1 "On now", Stage 2 "Coming next", 3–4 "Later"; modules outside the plan say "(not in your plan)".
5. Type `/admin/1/todays-close` in the address bar → ✅ 403.

### N. Stage 2 — Today's close
1. Central → stage **2** → Save → clinic Sync now.
2. ✅ Finance & Treasury and Insurance appear; **Today's close** is first under Finance.
3. Record a cash payment and a cash expense today → open **Today's close** →
   ✅ Collected (by method), Cash in the drawer = cash in − cash expenses, Patients owe, Installments due/overdue.
4. Pick yesterday in the date box → ✅ numbers change to that day. Print → ✅ print dialog.
5. Log in as a receptionist → ✅ no Today's close (owner/accountant only).

### O. Stage 3 and 4
1. Stage **3** → sync → ✅ treatment plans, prescriptions, the chart, doctor's chairside panel and reception follow-up list are back.
2. Stage **4** → sync → ✅ Labs, Inventory, Staff, Payroll (if in plan) appear; **Coming next** disappears from the sidebar.

### P. Central form keeps your edits
1. Central → clinic → change the stage → Save → change the plan → Save.
2. Reload → ✅ both changes kept (before the fix the second save put the stage back).

### Clean up
- Remove the three `LICENSE_*` lines from `.env` → `docker exec clinic_app sh docker/optimize.sh` → `docker restart clinic_nginx`.
- Central local data lives in `doxamigo-central/.wrangler/` — delete that folder to start fresh.

### Known issue found while writing this guide
In Docker, the scheduler container has a different "hardware fingerprint" from the web container,
so the **automatic** 30-minute sync is refused (409) — only **Sync now** works. Recreating
containers also changes the fingerprint. To fix next (see the plan): a fixed machine id for Docker installs.
