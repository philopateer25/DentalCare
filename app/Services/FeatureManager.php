<?php

namespace App\Services;

use App\Models\Practice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

class FeatureManager
{
    /**
     * Feature Definitions catalog.
     * System-only features can only be enabled/disabled by System Super Admin.
     */
    public const FEATURES = [
        '3d_model' => [
            'key' => '3d_model',
            'name' => '3D Odontogram Viewer',
            'category' => 'Clinical',
            'system_only' => true,
            'default' => false,
        ],
        'whatsapp' => [
            'key' => 'whatsapp',
            'name' => 'WhatsApp Integration',
            'category' => 'Communication',
            'system_only' => true,
            'default' => false,
        ],
        'ai_analysis' => [
            'key' => 'ai_analysis',
            'name' => 'AI X-Ray Analysis',
            'category' => 'Clinical',
            'system_only' => true,
            'default' => false,
        ],
        'inventory' => [
            'key' => 'inventory',
            'name' => 'Inventory Management',
            'category' => 'Operations',
            'system_only' => false,
            'default' => true,
        ],
        'finance' => [
            'key' => 'finance',
            'name' => 'Finance & Billing',
            'category' => 'Finance',
            'system_only' => false,
            'default' => true,
        ],
        'installments' => [
            'key' => 'installments',
            'name' => 'Installment Plans',
            'category' => 'Finance',
            'system_only' => false,
            'default' => true,
        ],
        'labs' => [
            'key' => 'labs',
            'name' => 'Dental Labs & Orders',
            'category' => 'Clinical',
            'system_only' => false,
            'default' => true,
        ],
        'insurance' => [
            'key' => 'insurance',
            'name' => 'Insurance & Claims',
            'category' => 'Finance',
            'system_only' => false,
            'default' => true,
        ],
        'payroll' => [
            'key' => 'payroll',
            'name' => 'Staff Payroll Slips',
            'category' => 'HR',
            'system_only' => false,
            'default' => true,
        ],
    ];

    /**
     * Get all feature keys.
     */
    public static function getAllKeys(): array
    {
        return array_keys(self::FEATURES);
    }

    /**
     * Get options array formatted for Filament select/checkbox list.
     */
    public static function getOptions(): array
    {
        $options = [];
        foreach (self::FEATURES as $key => $meta) {
            $prefix = $meta['system_only'] ? '[System Only] ' : '';
            $options[$key] = $prefix . $meta['name'];
        }
        return $options;
    }

    /**
     * Check if a feature is system-only.
     */
    public static function isSystemOnly(string $featureKey): bool
    {
        return self::FEATURES[$featureKey]['system_only'] ?? false;
    }

    /**
     * Check if a feature is enabled for a given practice or current tenant.
     */
    public static function isEnabled(string $featureKey, ?Practice $practice = null): bool
    {
        if (!$practice) {
            $practice = Filament::getTenant() ?? Auth::user()?->practice;
        }

        if (!$practice) {
            return self::FEATURES[$featureKey]['default'] ?? true;
        }

        return $practice->hasFeature($featureKey);
    }

    /**
     * Tenant-safe feature modification logic.
     * Prevents clinic admins from enabling system-only features or altering other practices.
     */
    public static function updatePracticeFeatures(Practice $targetPractice, array $requestedFeatures, User $actingUser): array
    {
        $isSuperAdmin = $actingUser->hasRole('super_admin') || $actingUser->role === 'super_admin' || $actingUser->role === 'developer';

        // Check tenancy permission: acting user must belong to target practice or be a super admin/developer
        if (!$isSuperAdmin && (int)$actingUser->practice_id !== (int)$targetPractice->id) {
            abort(403, 'Unauthorized cross-tenant feature configuration attempt.');
        }

        $currentFeatures = $targetPractice->features ?? [];
        if (!is_array($currentFeatures)) {
            $currentFeatures = [];
        }

        $finalFeatures = [];

        foreach (self::FEATURES as $key => $meta) {
            $requestedEnabled = in_array($key, $requestedFeatures, true) || (isset($requestedFeatures[$key]) && $requestedFeatures[$key] === true);

            if ($meta['system_only'] && !$isSuperAdmin) {
                // Clinic users cannot change system-only features; keep current state
                $currentlyEnabled = $targetPractice->hasFeature($key);
                if ($currentlyEnabled) {
                    $finalFeatures[] = $key;
                }
            } else {
                if ($requestedEnabled) {
                    $finalFeatures[] = $key;
                }
            }
        }

        $targetPractice->update(['features' => array_values(array_unique($finalFeatures))]);

        return $targetPractice->features;
    }
}
