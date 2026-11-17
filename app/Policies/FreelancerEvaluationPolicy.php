<?php

namespace App\Policies;

use App\Models\FreelancerEvaluation;
use App\Models\User;

/**
 * Role permissions for FreelancerEvaluation. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class FreelancerEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('freelancers.view');
    }

    public function view(User $user, FreelancerEvaluation $record): bool
    {
        return $user->can('freelancers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('freelancers.manage');
    }

    public function update(User $user, FreelancerEvaluation $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function delete(User $user, FreelancerEvaluation $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function restore(User $user, FreelancerEvaluation $record): bool
    {
        return $user->can('freelancers.manage');
    }

    public function forceDelete(User $user, FreelancerEvaluation $record): bool
    {
        return false;
    }
}
