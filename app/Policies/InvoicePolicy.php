<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        if (!\App\Services\FeatureManager::isEnabled('finance', $user->practice)) {
            return false;
        }

        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->practice_id === $invoice->practice_id
            && $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->practice_id === $invoice->practice_id
            && $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->practice_id === $invoice->practice_id
            && $user->hasAnyRole(['clinic_admin', 'super_admin']);
    }
}
