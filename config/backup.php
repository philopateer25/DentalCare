<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage Disk & Directory
    |--------------------------------------------------------------------------
    |
    | Defines where backups are stored. By default, backups are saved inside
    | the isolated storage/app/backups folder, which is excluded from public
    | web access to protect confidential patient and clinical data.
    |
    */
    'disk' => env('BACKUP_DISK', 'local'),

    'directory' => env('BACKUP_DIRECTORY', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Settings
    |--------------------------------------------------------------------------
    |
    | Dental and medical clinic data is sensitive health information (PHI).
    | When enabled, backups are encrypted using AES-256-CBC with an HMAC-SHA256
    | authentication signature (Encrypt-then-MAC). This guarantees that
    | stolen drives or copied files cannot be read without the encryption key.
    |
    */
    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),
        'cipher' => 'AES-256-CBC',
        'key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Inclusions
    |--------------------------------------------------------------------------
    |
    | Specify which components are included in each daily backup.
    |
    */
    'include' => [
        'database' => true,

        // Directories relative to base_path()
        'directories' => [
            'storage/app/public',
            'storage/app/voice_notes',
            'storage/app/private',
        ],

        // Standalone files relative to base_path()
        'files' => [
            'storage/app/license.json',
            '.env',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy (Auto-Pruning)
    |--------------------------------------------------------------------------
    |
    | Prevent the clinic device's hard drive from running out of disk space.
    | Old backups beyond these limits are automatically purged.
    |
    */
    'retention' => [
        'days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'max_backups' => (int) env('BACKUP_MAX_COUNT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Working Directory
    |--------------------------------------------------------------------------
    |
    | Temporary workspace used during archive compilation and encryption.
    | Cleaned up immediately upon completion.
    |
    */
    'temp_directory' => storage_path('app/backup-temp'),

    /*
    |--------------------------------------------------------------------------
    | Backup Protection & Deletion Security
    |--------------------------------------------------------------------------
    |
    | To protect clinic backups against accidental deletion, malicious staff,
    | or unauthorized tampering, deleting any backup requires entering
    | this unique security password.
    |
    */
    'security' => [
        'delete_password' => env('BACKUP_DELETE_PASSWORD', 'DentalCare@SecureDelete2026'),
    ],
];

