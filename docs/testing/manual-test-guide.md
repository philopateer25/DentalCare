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

## Phase 4 — switching from paper

Works at any rollout stage. Opening balances become invoices; they show in Finance once the clinic reaches stage 2.

### Q. Import patients and what they owe
1. Sidebar → **Import patients** → **Download template (.xlsx)** → open it in Excel.
2. Keep the two sample rows and add a few of your own. Include some mistakes on purpose:
   - a row without a name;
   - a phone that already belongs to a patient;
   - "abc" as the amount owed;
   - the same phone twice;
   - Arabic digits like ٠١٠١٢٣٤٥٦٧٨, a phone without its leading zero, a date like 1/12/2026.
3. Upload → **Check file** → ✅ "N ready" + a red list of the bad rows with the reason; **nothing is saved yet** (Patients count unchanged).
4. **Import N patients** → ✅ "Done": patients, opening balances and installment plans counted.
5. Open a patient who owed money → ✅ an invoice "Opening balance (paper records)" with the full amount due;
   with installments → Patient Financing shows the schedule starting on your date (or next month).
6. Import the same file again → ✅ every row says "Already a patient"; nothing is duplicated.

### R. Scan a paper card with a phone
The phone must reach this PC over the clinic Wi-Fi, so `localhost` will not work for this test.
1. Find the PC's address: `ipconfig` → IPv4 (e.g. 192.168.1.10). Either open DOXamigo on the PC as
   `http://192.168.1.10/admin`, or add `CLINIC_LAN_URL=http://192.168.1.10` to `.env` → optimize + restart nginx.
   (Windows Firewall may ask to allow Docker on private networks — allow it.)
2. Open a patient → **Scan paper card** → ✅ a dialog with the address (and a QR code once the QR library is installed) and "Waiting for photos…".
   On `localhost` without CLINIC_LAN_URL → ✅ it explains that a phone can't open localhost.
3. On the phone (same Wi-Fi) open the address → **Take photos** → take 2 → **Save to patient file** → ✅ "2 photos saved".
4. Within 3 seconds the PC dialog shows ✅ "2 photos received" → **Done — show the photos** → Files tab lists two "Paper card – …" files; Download opens them.
5. Open the same phone link again → ✅ "expired or was already used". Wait 10 minutes with a fresh link → ✅ expired.

### S. Returning patient who only has a paper card
1. Patients → search a name that doesn't exist → ✅ "Create from paper card" in the empty list, name pre-filled.
2. Enter the phone → **Create and scan card** → ✅ the new patient opens with the scan dialog already showing.
3. Try a phone that already exists → ✅ red "Already a patient: … (file number)" with an **Open** button; nothing created.

## Phase 5 — dropdowns you can add to

Prescriptions need rollout stage 3 or more; expenses need stage 2 and a plan with finance.

### T. Type once, pick next time
1. A patient → **Prescriptions** → New → tap **Medication** without typing → ✅ a list opens (Amoxicillin 500mg …).
2. Type a medication that isn't there (e.g. `Flagyl 500mg`) → ✅ the first line says **Add “Flagyl 500mg”** → pick it, fill the rest, save.
3. New prescription again → tap Medication → ✅ **Flagyl 500mg is first**. The same goes for Dosage, Frequency and Duration (the last values you used come first).
4. Expenses → New → **Accounting category** → type `Water bottles` → **Add “Water bottles”** → save → next expense: ✅ it's in the list.

### U. Quick phrases and tags
1. Treatment plan → add a procedure → **Quick phrase** → tap "Well tolerated", then "Rubber dam used" → ✅ Notes reads `Well tolerated. Rubber dam used`.
2. Type your own phrase → **Add “…”** → ✅ it's added to the note and offered next time.
3. Patient → Edit → medical history → **Other medical conditions** → type a new one → save → on another patient ✅ it's suggested.

### U2. Dental chart (3D / 2D diagnosis page)
1. A patient → **Dental chart** → pick a tooth → **Diagnosis** tab → tap **Clinical diagnosis** → ✅ list opens without typing.
2. Type `Cracked tooth` → ✅ **+ Add “Cracked tooth”** → pick it → ✅ saved on the tooth.
3. Another tooth → Clinical diagnosis → ✅ **Cracked tooth** is first, marked "Used here".
4. Findings tab → under Notes → **+ Quick phrase** → tap "Sensitive to cold" → ✅ added to the tooth note.
5. Crown / Implant / Missing teeth: material, implant system and reason missing work the same way.
   Severity, urgency, mobility, pulp and periapical diagnosis stay fixed lists on purpose (standard scales).

### V. Dropdown lists screen (admin)
1. Settings → **Dropdown lists** → ✅ everything typed above, with "Times used".
2. Filter by list, **Remove** a typo → ✅ it no longer appears in the dropdown.
3. Log in as a doctor → ✅ no Dropdown lists menu (doctors add values by typing; admins clean up).

## Phase 6 - native Windows install (no Docker)

Needs a spare PC or a virtual machine with Windows 10/11 64-bit. Don't use your development PC:
the installer creates services and uses port 80. Building the .exe needs Inno Setup 6 (see `deploy/windows/README.md`).

### W. Build
1. On your dev PC: `.\deploy\windowsuild.ps1 -PinHashes -AllowNoProductionKey` (first time) -> check `versions.json` got hashes.
2. Result: `deploy\windowsuild\DOXamigo-Setup-<version>.exe`.

### X. Install on a clean PC
1. Central app -> new clinic -> copy the enrollment code.
   For a test installer: the central app must be reachable from that PC (deployed, or your laptop's address).
2. Run the .exe -> ✅ warning only if the PC is weak (under 4 GB RAM / 20 GB free).
3. Enter clinic name, admin email, enrollment code -> Install.
4. ✅ Last page shows the email + a temporary password (write it down) and two addresses: this PC and `http://192.168.x.x`.
5. Desktop shortcut **DOXamigo** -> log in -> ✅ onboarding wizard (branches, chairs), then the dashboard; License page shows "Active".
6. `services.msc` -> ✅ DOXamigo-Web, -PHP-1..4, -Queue, -Scheduler are Running, startup Automatic.

### Y. Other devices
1. Reception PC / phone on the same Wi-Fi -> open the `http://192.168.x.x` address -> ✅ login page.
2. Two people working at the same time -> ✅ no waiting (4 PHP workers).
3. Patient -> **Scan paper card** -> ✅ the dialog shows the 192.168 address (phones can open it).

### Z. Survives a reboot and an update
1. Restart the PC, don't log in to Windows -> from the phone -> ✅ DOXamigo works (services start on boot).
2. After 30+ minutes -> central app -> ✅ "last seen" updates on its own (scheduler heartbeat; no 409).
3. Add a patient with a photo -> run a newer DOXamigo-Setup .exe -> ✅ no questions asked, patient and photo still there.
4. Apps -> uninstall -> ✅ `C:\DOXamigo\data` still exists; reinstall -> ✅ same data.

## Backups

### AA. Who sees backups
A backup is the whole computer: every clinic on it plus the `.env` key. Restoring one overwrites everything.
1. Log in as a **clinic admin** on an install with several clinics (your Docker dev has 7) -> ✅ no **Backup & Security** menu; `/admin/<id>/backup-manager` -> 403.
2. Log in as the **super admin** / developer -> ✅ the backup list is there.
3. A **doctor** -> ✅ no backup menu, even on a one-clinic PC.
4. A real clinic PC (one clinic) -> its **clinic admin** ✅ sees and manages the backups.
5. On a shared install a clinic admin uses **License -> Export data** instead (own clinic only).

### AB. Daily backup at 02:00
1. `docker logs clinic_scheduler` -> ✅ "Starting: php artisan schedule:work" (not "Starting PHP-FPM").
2. Next morning -> Backup & Security -> ✅ a backup from about 02:00; Download works.
3. Without waiting: `docker exec clinic_scheduler php artisan schedule:test --name="backup:run"` -> ✅ a new backup appears within a minute.

## Clinic install with Docker + Watchtower (the default)

Needs a spare PC/VM with Windows 10/11, virtualization on, and the images published (private) on Docker Hub.

### AC. Install
1. Dev PC: `.\deploy\docker\publish.ps1 -Channel pilot` -> ✅ images pushed. Then `.\deploy\dockeruild-installer.ps1` -> `DOXamigo-Docker-Setup.exe`.
2. Clinic PC: run it as Administrator -> clinic name, admin email, enrollment code, update token, channel `pilot`.
3. ✅ It ends with the email + a temporary password and the `http://192.168.x.x` address; desktop shortcut works.
4. Log in -> ✅ onboarding wizard; Patients list is **empty** (no demo patients); only your admin under Users.
5. `http://<pc>/quick-login` -> ✅ 404.

### AD. Automatic update
1. Change something visible, commit, `publish.ps1 -Channel pilot`.
2. On the clinic PC (instead of waiting for 03:30): `docker exec doxamigo_watchtower /watchtower --run-once --label-enable`
   -> ✅ log says the containers were updated; the change is visible; patients still there.
3. With a new migration: ✅ `C:\DOXamigoackups` gets a backup just before the update.
4. Restart the PC and sign in to Windows -> ✅ DOXamigo comes back by itself.

### Clean up
- Remove the three `LICENSE_*` lines from `.env` → `docker exec clinic_app sh docker/optimize.sh` → `docker restart clinic_nginx`.
- Central local data lives in `doxamigo-central/.wrangler/` — delete that folder to start fresh.

### Known issue found while writing this guide
In Docker, the scheduler container has a different "hardware fingerprint" from the web container,
so the **automatic** 30-minute sync is refused (409) — only **Sync now** works. Recreating
containers also changes the fingerprint. To fix next (see the plan): a fixed machine id for Docker installs.
