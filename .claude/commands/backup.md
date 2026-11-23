# Clinic Backup Command

Manage encrypted clinic backups (database, storage files, patient voice notes).

## Commands
- **Create encrypted backup:**
  ```bash
  php artisan backup:run
  ```
- **List available backups:**
  ```bash
  php artisan backup:list
  ```
- **Prune expired backups according to retention policy:**
  ```bash
  php artisan backup:clean
  ```

## Security & Encryption
- Backups use AES-256-CBC with HMAC-SHA256 (Encrypt-then-MAC).
- Stored in `storage/app/backups/`.
- Deleting a backup requires the security password defined in `config/backup.php` (`BACKUP_DELETE_PASSWORD`).
