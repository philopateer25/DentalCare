<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasAnyRole(['clinic_admin', 'super_admin', 'developer']);
    }

    public function view(User $authUser, User $targetUser): bool
    {
        if ($authUser->hasRole('developer')) {
            return true;
        }

        return $authUser->practice_id === $targetUser->practice_id
            && $authUser->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasAnyRole(['clinic_admin', 'super_admin', 'developer']);
    }

    public function update(User $authUser, User $targetUser): bool
    {
        if ($authUser->hasRole('developer')) {
            return true;
        }

        if ($targetUser->hasRole('developer')) {
            return false;
        }

        if ($targetUser->hasRole('super_admin') && !$authUser->hasRole('super_admin')) {
            return false;
        }

        return $authUser->practice_id === $targetUser->practice_id
            && $authUser->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    public function delete(User $authUser, User $targetUser): bool
    {
        if ($authUser->id === $targetUser->id) {
            return false; // Prevent self-deletion
        }

        if ($authUser->hasRole('developer')) {
            return true;
        }

        if ($targetUser->hasRole('developer')) {
            return false;
        }

        if ($targetUser->hasRole('super_admin') && !$authUser->hasRole('super_admin')) {
            return false;
        }

        return $authUser->practice_id === $targetUser->practice_id
            && $authUser->hasAnyRole(['clinic_admin', 'super_admin']);
    }
}
