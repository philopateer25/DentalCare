<?php

namespace App\Policies;

use App\Models\LabOrder;
use App\Models\User;

class LabOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function view(User $user, LabOrder $labOrder): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function update(User $user, LabOrder $labOrder): bool
    {
        return $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function delete(User $user, LabOrder $labOrder): bool
    {
        return $user->hasAnyRole(['clinic_admin', 'super_admin']);
    }
}
