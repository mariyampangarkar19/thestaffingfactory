<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Role permissions for Payment. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, Payment $record): bool
    {
        return $user->can('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function update(User $user, Payment $record): bool
    {
        return $user->can('finance.manage');
    }

    public function delete(User $user, Payment $record): bool
    {
        return false;
    }

    public function restore(User $user, Payment $record): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $record): bool
    {
        return false;
    }
}
