<?php

namespace App\Policies;

use App\Models\PayRate;
use App\Models\User;

/**
 * Role permissions for PayRate. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class PayRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, PayRate $record): bool
    {
        return $user->can('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function update(User $user, PayRate $record): bool
    {
        return $user->can('finance.manage');
    }

    public function delete(User $user, PayRate $record): bool
    {
        return $user->can('finance.manage');
    }

    public function restore(User $user, PayRate $record): bool
    {
        return $user->can('finance.manage');
    }

    public function forceDelete(User $user, PayRate $record): bool
    {
        return false;
    }
}
