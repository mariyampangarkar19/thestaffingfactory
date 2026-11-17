<?php

namespace App\Policies;

use App\Models\StaffRequest;
use App\Models\User;

/**
 * Role permissions for StaffRequest. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class StaffRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('requests.view');
    }

    public function view(User $user, StaffRequest $record): bool
    {
        return $user->can('requests.view');
    }

    public function create(User $user): bool
    {
        return $user->can('requests.manage');
    }

    public function update(User $user, StaffRequest $record): bool
    {
        return $user->can('requests.manage');
    }

    public function delete(User $user, StaffRequest $record): bool
    {
        return $user->can('requests.manage');
    }

    public function restore(User $user, StaffRequest $record): bool
    {
        return $user->can('requests.manage');
    }

    public function forceDelete(User $user, StaffRequest $record): bool
    {
        return false;
    }
}
