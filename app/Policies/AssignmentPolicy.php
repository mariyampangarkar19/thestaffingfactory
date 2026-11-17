<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

/**
 * Role permissions for Assignment. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('requests.view');
    }

    public function view(User $user, Assignment $record): bool
    {
        return $user->can('requests.view');
    }

    public function create(User $user): bool
    {
        return $user->can('assignments.manage');
    }

    public function update(User $user, Assignment $record): bool
    {
        return $user->can('assignments.manage');
    }

    public function delete(User $user, Assignment $record): bool
    {
        return false;
    }

    public function restore(User $user, Assignment $record): bool
    {
        return false;
    }

    public function forceDelete(User $user, Assignment $record): bool
    {
        return false;
    }
}
