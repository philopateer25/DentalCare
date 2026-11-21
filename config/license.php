<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central License Server
    |--------------------------------------------------------------------------
    |
    | The URL of the DentalCare Central authentication server and the shared
    | API key used to authenticate requests from this client installation.
    |
    */

    'server_url' => env('LICENSE_SERVER_URL', 'https://auth.dentalcare.app'),

    'api_key' => env('LICENSE_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    |
    | null (default) = enforce everywhere except the `local` and `testing`
    | environments. Set LICENSE_ENFORCE=true to test enforcement locally.
    | `local` is always bypassed by LicenseManager so developers are never
    | locked out.
    |
    */

    'enforce' => env('LICENSE_ENFORCE'),

    /*
    |--------------------------------------------------------------------------
    | License Signing Keys (Ed25519 public keys, base64)
    |--------------------------------------------------------------------------
    |
    | Replies from the central app are signed with its private key; only the
    | public half lives here. Production keys are committed in code on purpose
    | so they can't be swapped through a clinic's .env. Outside production,
    | extra keys can be added with LICENSE_PUBLIC_KEYS="k1:BASE64,k2:BASE64".
    | Several keys may be listed at once to rotate keys without downtime.
    |
    */

    'public_keys' => array_merge(
        [
            // 'k1' => 'BASE64_PUBLIC_KEY', // add the central app's key here when it is generated
        ],
        env('APP_ENV') === 'production'
            ? []
            : \App\Services\Licensing\LicenseEnvelope::parseKeyList(env('LICENSE_PUBLIC_KEYS')),
    ),

    /*
    |--------------------------------------------------------------------------
    | Subscription Lock Steps (defaults; the signed payload can override)
    |--------------------------------------------------------------------------
    |
    | After `paid_until`: warning banner from 7 days before, read-only after
    | `read_only_after_days`, fully locked `lock_after_days` later. All
    | boundaries fall on midnight, so a clinic is never cut off mid-session.
    |
    */

    'billing_warning_days' => 7,

    'read_only_after_days' => 3,

    'lock_after_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Allowed While Restricted
    |--------------------------------------------------------------------------
    |
    | Routes that keep working in read-only and locked mode, so staff can log
    | in, reach the license page, sync, enter an unlock code and export data.
    |
    */

    'always_allowed_routes' => [
        'filament.admin.auth.*',
        'filament.admin.pages.license',
        'filament.system.auth.*',
        'license.export',
        'locale.switch',
        'livewire.update',
        'logout',
        'login',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models Writable In Read-Only Mode
    |--------------------------------------------------------------------------
    |
    | Logging in and contacting support must keep working.
    |
    */

    'read_only_exempt_models' => [
        \App\Models\User::class,
        \App\Models\SupportTicket::class,
        \App\Models\SupportTicketReply::class,
        \Illuminate\Notifications\DatabaseNotification::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | How many hours the application may run offline before locking out.
    | After this period expires without a successful heartbeat, the user
    | is forced to go online and re-validate.
    |
    */

    'grace_period_hours' => (int) env('LICENSE_GRACE_PERIOD_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Alert Thresholds (hours)
    |--------------------------------------------------------------------------
    |
    | Tiered alert system that progressively warns users as offline time
    | approaches the grace period limit.
    |
    |  - warning:  Soft banner shown at the top of every page.
    |  - urgent:   Modal popup shown every 30 minutes.
    |  - lockout:  Full application lockout (equals grace_period_hours).
    |
    */

    'alert_warning_hours' => (int) env('LICENSE_ALERT_WARNING_HOURS', 48),

    'alert_urgent_hours' => (int) env('LICENSE_ALERT_URGENT_HOURS', 66),

    /*
    |--------------------------------------------------------------------------
    | Clock Tick Interval (minutes)
    |--------------------------------------------------------------------------
    |
    | How often the scheduler persists clock state to the encrypted file
    | and database. Lower values = more tamper-resistant but more I/O.
    |
    */

    'tick_interval_minutes' => (int) env('LICENSE_TICK_INTERVAL', 10),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Interval
    |--------------------------------------------------------------------------
    |
    | The scheduler sends a heartbeat every 30 minutes (routes/console.php).
    | Staff can also press "Sync now" on the License page.
    |
    */

    'heartbeat_interval_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | The cipher used to encrypt the local license and clock state files.
    | Uses Laravel's APP_KEY as the encryption key.
    |
    */

    'cipher' => 'aes-256-gcm',

    /*
    |--------------------------------------------------------------------------
    | File Paths
    |--------------------------------------------------------------------------
    |
    | Paths to the encrypted state files within the storage directory.
    |
    */

    'license_file' => storage_path('app/license.enc'),

    'clock_file' => storage_path('app/license_clock.enc'),

];
