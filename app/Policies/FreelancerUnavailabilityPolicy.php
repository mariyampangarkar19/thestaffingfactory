<?php

namespace App\Policies;

use App\Models\FreelancerUnavailability;
use App\Models\User;

/**
 * Role permissions for FreelancerUnavailability. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class FreelancerUnavailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('freelancers.view');
    }

    public function view(User $user, FreelancerUnavailability $record): bool
    {
        return $user->can('freelancers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('freelancers.manage');
    }

    public function update(User $user, FreelancerUnavailability $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function delete(User $user, FreelancerUnavailability $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function restore(User $user, FreelancerUnavailability $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function forceDelete(User $user, FreelancerUnavailability $record): bool
    {
        return false;
    }
}
