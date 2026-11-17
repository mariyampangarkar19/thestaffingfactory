<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

/**
 * Role permissions for Shift. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('requests.view');
    }

    public function view(User $user, Shift $record): bool
    {
        return $user->can('requests.view');
    }

    public function create(User $user): bool
    {
        return $user->can('assignments.manage');
    }

    public function update(User $user, Shift $record): bool
    {
        return $user->can('assignments.manage');
    }

    public function delete(User $user, Shift $record): bool
    {
        return $user->can('assignments.manage');
    }

    public function restore(User $user, Shift $record): bool
    {
        return $user->can('assignments.manage');
    }

    public function forceDelete(User $user, Shift $record): bool
    {
        return false;
    }
}
