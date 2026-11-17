<?php

namespace App\Policies;

use App\Models\StaffRequestPosition;
use App\Models\User;

/**
 * Role permissions for StaffRequestPosition. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class StaffRequestPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('requests.view');
    }

    public function view(User $user, StaffRequestPosition $record): bool
    {
        return $user->can('requests.view');
    }

    public function create(User $user): bool
    {
        return $user->can('requests.manage');
    }

    public function update(User $user, StaffRequestPosition $record): bool
    {
        return $user->can('requests.manage');
    }

    public function delete(User $user, StaffRequestPosition $record): bool
    {
        return $user->can('requests.manage');
    }

    public function restore(User $user, StaffRequestPosition $record): bool
    {
        return $user->can('requests.manage');
    }

    public function forceDelete(User $user, StaffRequestPosition $record): bool
    {
        return false;
    }
}
