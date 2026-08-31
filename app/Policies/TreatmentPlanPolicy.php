<?php

namespace App\Policies;

use App\Models\TreatmentPlan;
use App\Models\User;

class TreatmentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function view(User $user, TreatmentPlan $treatmentPlan): bool
    {
        return $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function update(User $user, TreatmentPlan $treatmentPlan): bool
    {
        return $user->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']);
    }

    public function delete(User $user, TreatmentPlan $treatmentPlan): bool
    {
        return $user->hasAnyRole(['clinic_admin', 'super_admin']);
    }
}
