<?php

namespace App\Policies;

use App\Models\MaintenanceRecord;
use App\Models\User;

class MaintenanceRecordPolicy
{
    private const VIEW_ROLES = ['doctor', 'secretary', 'clinic_admin', 'super_admin'];

    private const MANAGE_ROLES = ['secretary', 'clinic_admin', 'super_admin'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::VIEW_ROLES);
    }

    public function view(User $user, MaintenanceRecord $record): bool
    {
        return $user->practice_id === $record->practice_id
            && $user->hasAnyRole(self::VIEW_ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function update(User $user, MaintenanceRecord $record): bool
    {
        return $user->practice_id === $record->practice_id
            && $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function delete(User $user, MaintenanceRecord $record): bool
    {
        return $user->practice_id === $record->practice_id
            && $user->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasAnyRole(['clinic_admin', 'super_admin']);
    }
}
