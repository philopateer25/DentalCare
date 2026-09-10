<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        if (!\App\Services\FeatureManager::isEnabled('inventory', $user->practice)) {
            return false;
        }

        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin', 'developer']);
    }

    public function view(User $user, InventoryItem $item): bool
    {
        if ($user->hasRole('developer')) {
            return true;
        }

        return (int) $user->practice_id === (int) $item->practice_id
            && $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['secretary', 'clinic_admin', 'super_admin', 'developer']);
    }

    public function update(User $user, InventoryItem $item): bool
    {
        if ($user->hasRole('developer')) {
            return true;
        }

        return (int) $user->practice_id === (int) $item->practice_id
            && $user->hasAnyRole(['secretary', 'clinic_admin', 'super_admin']);
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        if ($user->hasRole('developer')) {
            return true;
        }

        return (int) $user->practice_id === (int) $item->practice_id
            && $user->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    public function adjustStock(User $user, InventoryItem $item): bool
    {
        if ($user->hasRole('developer')) {
            return true;
        }

        return (int) $user->practice_id === (int) $item->practice_id
            && $user->hasAnyRole(['clinic_admin', 'super_admin', 'secretary']);
    }
}
