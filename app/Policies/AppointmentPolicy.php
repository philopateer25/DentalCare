<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['secretary', 'clinic_admin', 'super_admin']);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->hasAnyRole(['clinic_admin', 'super_admin']);
    }
}
