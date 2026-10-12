<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin', 'developer']);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        if ($user->hasAnyRole(['developer', 'super_admin'])) {
            return true;
        }

        return (int) $user->practice_id === (int) $supplier->practice_id
            && $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin', 'developer']);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        if ($user->hasAnyRole(['developer', 'super_admin'])) {
            return true;
        }

        return (int) $user->practice_id === (int) $supplier->practice_id
            && $user->hasAnyRole(['doctor', 'secretary', 'clinic_admin']);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        if ($user->hasAnyRole(['developer', 'super_admin'])) {
            return true;
        }

        return (int) $user->practice_id === (int) $supplier->practice_id
            && $user->hasAnyRole(['clinic_admin']);
    }
}
