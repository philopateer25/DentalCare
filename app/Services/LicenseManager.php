<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LicenseManager
{
    private string $licensePath;

    public function __construct()
    {
        $this->licensePath = storage_path('app/license.json');
    }

    /**
     * Get the current locally cached license state.
     */
    public function getLicenseState(): array
    {
        if (!File::exists($this->licensePath)) {
            if (app()->environment('local')) {
                return [
                    'status' => 'active',
                    'clinic_id' => 'CLINIC-LOCAL-DEV',
                    'grace_period_expires_at' => null,
                    'last_synced_at' => now()->toDateTimeString(),
                ];
            }

            return [
                'status' => 'inactive',
                'clinic_id' => null,
                'grace_period_expires_at' => null,
                'last_synced_at' => null,
                'error' => 'License file not found. Please enroll this installation.'
            ];
        }

        try {
            $data = json_decode(File::get($this->licensePath), true);
            
            // Basic validation
            if (!isset($data['status'])) {
                throw new \Exception("Invalid license data structure");
            }
            
            // Check if grace period expired (if we are in offline mode / cannot sync)
            if (isset($data['grace_period_expires_at'])) {
                if (now()->isAfter($data['grace_period_expires_at']) && $data['status'] === 'active') {
                    $data['status'] = 'expired_grace_period';
                }
            }

            return $data;
        } catch (\Exception $e) {
            Log::error("Failed to read local license: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'License file corrupted.'
            ];
        }
    }

    /**
     * Update the locally cached license state.
     */
    public function updateLicenseState(array $newState): void
    {
        $currentState = $this->getLicenseState();
        
        $merged = array_merge($currentState, $newState, [
            'last_synced_at' => now()->toDateTimeString()
        ]);
        
        File::put($this->licensePath, json_encode($merged, JSON_PRETTY_PRINT));
    }

    /**
     * Check if the application should be locked out.
     */
    public function isLockedOut(): bool
    {
        if (app()->environment('local')) {
            return false;
        }

        $state = $this->getLicenseState();
        $status = $state['status'] ?? 'inactive';
        
        // Allowed statuses
        return !in_array($status, ['active']);
    }
}
