<?php

namespace App\Policies;

use App\Models\JobRole;
use App\Models\User;

/**
 * Role permissions for JobRole. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class JobRolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('requests.view');
    }

    public function view(User $user, JobRole $record): bool
    {
        return $user->can('requests.view');
    }

    public function create(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, JobRole $record): bool
    {
        return $user->can('settings.manage');
    }

    public function delete(User $user, JobRole $record): bool
    {
        return $user->can('settings.manage');
    }

    public function restore(User $user, JobRole $record): bool
    {
        return $user->can('settings.manage');
    }

    public function forceDelete(User $user, JobRole $record): bool
    {
        return false;
    }
}
