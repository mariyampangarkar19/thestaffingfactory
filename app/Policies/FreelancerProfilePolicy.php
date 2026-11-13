<?php

namespace App\Policies;

use App\Models\FreelancerProfile;
use App\Models\User;

/**
 * Agency scoping is already guaranteed by the tenant scope + RLS. Policies decide what each ROLE may do.
 */
class FreelancerProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('freelancers.view');
    }

    public function view(User $user, FreelancerProfile $profile): bool
    {
        return $user->can('freelancers.view') || $profile->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('freelancers.manage');
    }

    public function update(User $user, FreelancerProfile $profile): bool
    {
        return $user->can('freelancers.manage');
    }

    public function delete(User $user, FreelancerProfile $profile): bool
    {
        return $user->can('freelancers.manage');
    }

    public function restore(User $user, FreelancerProfile $profile): bool
    {
        return $user->can('freelancers.manage');
    }

    public function forceDelete(User $user, FreelancerProfile $profile): bool
    {
        return false; // Permanent erasure only through the data-protection request flow.
    }
}
