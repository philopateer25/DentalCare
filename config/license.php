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
    | Heartbeat Interval (hours)
    |--------------------------------------------------------------------------
    |
    | How often the scheduler attempts to send a heartbeat to the central
    | server to re-validate the license.
    |
    */

    'heartbeat_interval_hours' => (int) env('LICENSE_HEARTBEAT_INTERVAL', 4),

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
