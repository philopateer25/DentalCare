<?php

namespace App\Policies;

use App\Models\ExternalTreatment;
use App\Models\User;

class ExternalTreatmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function view(User $user, ExternalTreatment $externalTreatment): bool
    {
        return $user->practice_id === $externalTreatment->patient?->practice_id
            && $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function update(User $user, ExternalTreatment $externalTreatment): bool
    {
        return $user->practice_id === $externalTreatment->patient?->practice_id
            && $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function delete(User $user, ExternalTreatment $externalTreatment): bool
    {
        return $user->practice_id === $externalTreatment->patient?->practice_id
            && $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }
}
