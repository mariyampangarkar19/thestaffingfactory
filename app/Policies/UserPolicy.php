<?php

namespace App\Policies;

use App\Models\User;

/**
 * Team management is for agency owners (quote: "Team & Operator Management").
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function view(User $user, User $member): bool
    {
        return $user->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $member): bool
    {
        return $user->can('users.manage');
    }

    public function delete(User $user, User $member): bool
    {
        return false; // Accounts are deactivated, not deleted, so history keeps its author.
    }
}
