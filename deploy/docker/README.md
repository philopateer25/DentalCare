# DOXamigo on clinic PCs: Docker + Watchtower (the default)

The code ships only inside images. Clinics update themselves every night. The native Windows installer (`deploy/windows/`) is the fallback for PCs that can't run Docker (no hardware virtualization).

## On the clinic PC

```
C:\DOXamigo\
  docker-compose.yml     written by the installer (the compose text is inside deploy/install.ps1)
  .env                   settings + secrets (template inside deploy/install.ps1) (APP_KEY, machine id); Administrators only
  registry\config.json   read-only pull token for Watchtower; Administrators only
  backups\               encrypted backups (02:00 daily + before every update that changes the database)
```

The **database and uploads** live in Docker volumes (`doxamigo_data`, `doxamigo_storage`) and are never inside an image.

| Container | Image | Role |
|---|---|---|
| `doxamigo_app` | dentalcare-app | PHP. On every start: backup if the update changes the database, then migrations and caches |
| `doxamigo_worker` | dentalcare-app | Background jobs |
| `doxamigo_scheduler` | dentalcare-app | License sync every 30 min, license clock, backup at 02:00 |
| `doxamigo_web` | dentalcare-web | nginx on port 80 (or 8080), with the built frontend |
| `doxamigo_watchtower` | watchtower | At 03:30 Cairo time it pulls new images for the clinic's channel and recreates the DOXamigo containers (and only those) |

## Shipping a new version or a fix

```powershell
docker login                                    # once, with push rights
.\deploy\docker\publish.ps1 -Channel pilot      # 1-2 pilot clinics get it tonight
.\deploy\docker\publish.ps1 -Channel stable     # everyone, once the pilots are fine
```

`publish.ps1`:
1. Builds from the committed code.
2. Refuses to build if `config/license.php` has no production key, or if `.dockerignore` would let the database, backups or keys into the image.
3. Checks the built image for private files.
4. Pushes `:<version>` and `:<channel>`.

**Roll back:** push the previous version again as the channel tag, for example:

```powershell
git checkout <old tag>
.\deploy\docker\publish.ps1 -Channel stable
```

Every update that changes the database first leaves a backup in `C:\DOXamigo\backups`.

## Installing a clinic

```powershell
.\deploy\docker\build-installer.ps1     # -> deploy\docker\build\DOXamigo-Docker-Setup.exe (or convert deploy\install.ps1 in Win-PS2EXE)
```

Run the `.exe` on the clinic PC as Administrator. It:
1. Checks virtualization, RAM and disk.
2. Installs Docker Desktop if missing, and makes it start when Windows starts.
3. Asks for the clinic name, admin email, enrollment code and the read-only **update token**.
4. Writes `C:\DOXamigo`, then pulls the images and starts them.
5. Creates the clinic and its admin with a random password, shown once, and **no demo data**.
6. Activates the license.
7. Opens the firewall for private networks only, keeps the PC awake, and adds the desktop shortcut.
8. Optionally adds Tailscale; you type the key in, and it's never stored in the installer.

Running it again on an installed PC updates the setup and keeps all data. It never runs `docker compose down -v`.

## Before the first clinic

1. **Make the images private on Docker Hub** (`philohany12/dentalcare-app`, `-web`), and delete old tags that were built before `.dockerignore` excluded the database and keys.
2. **Create a read-only access token** in Docker Hub (Account settings → Personal access tokens → *Read-only*). It's the "update token" the installer asks for.
3. **Revoke the Tailscale key** that was written in the old `deploy/install.ps1` (and inside the old `DentalCare.exe`).
4. **Watchtower:** the compose file pins `nickfedor/watchtower:1.24.1`, the maintained fork, because `containrrr/watchtower` is archived. Tested on 2026-11-23 with Docker Engine 29.7 and a local registry: it found the new image, pulled it, and recreated app/worker/scheduler/web. The app backed up the database, ran the new migration and kept the data. Change the pinned version only after repeating that test.
5. **Encode the PHP code** (ionCube) in the image build, so the code isn't readable inside the container.
