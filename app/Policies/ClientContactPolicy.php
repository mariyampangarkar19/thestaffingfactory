<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\User;

/**
 * Role permissions for ClientContact. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class ClientContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user, ClientContact $record): bool
    {
        return $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.manage');
    }

    public function update(User $user, ClientContact $record): bool
    {
        return $user->can('clients.manage');
    }

    public function delete(User $user, ClientContact $record): bool
    {
        return $user->can('clients.manage');
    }

    public function restore(User $user, ClientContact $record): bool
    {
        return $user->can('clients.manage');
    }

    public function forceDelete(User $user, ClientContact $record): bool
    {
        return false;
    }
}
