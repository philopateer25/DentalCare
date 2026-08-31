<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['secretary', 'clinic_admin', 'super_admin']);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->hasAnyRole(['secretary', 'clinic_admin', 'super_admin']);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    public function restore(User $user, Patient $patient): bool
    {
        return $user->hasAnyRole(['super_admin']);
    }

    public function forceDelete(User $user, Patient $patient): bool
    {
        return $user->hasAnyRole(['super_admin']);
    }
}
