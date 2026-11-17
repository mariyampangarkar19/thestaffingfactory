<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Role permissions for Client. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user, Client $record): bool
    {
        return $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.manage');
    }

    public function update(User $user, Client $record): bool
    {
        return $user->can('clients.manage');
    }

    public function delete(User $user, Client $record): bool
    {
        return $user->can('clients.manage');
    }

    public function restore(User $user, Client $record): bool
    {
        return $user->can('clients.manage');
    }

    public function forceDelete(User $user, Client $record): bool
    {
        return false;
    }
}
