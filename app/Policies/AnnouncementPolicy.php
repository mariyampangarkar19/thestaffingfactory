<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Role permissions for Announcement. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('freelancers.view');
    }

    public function view(User $user, Announcement $record): bool
    {
        return $user->can('freelancers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('freelancers.manage');
    }

    public function update(User $user, Announcement $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function delete(User $user, Announcement $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function restore(User $user, Announcement $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function forceDelete(User $user, Announcement $record): bool
    {
        return false;
    }
}
