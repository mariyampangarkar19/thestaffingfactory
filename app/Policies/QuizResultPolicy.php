<?php

namespace App\Policies;

use App\Models\QuizResult;
use App\Models\User;

/**
 * Role permissions for QuizResult. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class QuizResultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('freelancers.view');
    }

    public function view(User $user, QuizResult $record): bool
    {
        return $user->can('freelancers.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, QuizResult $record): bool
    {
        return false;
    }

    public function delete(User $user, QuizResult $record): bool
    {
        return false;
    }

    public function restore(User $user, QuizResult $record): bool
    {
        return false;
    }

    public function forceDelete(User $user, QuizResult $record): bool
    {
        return false;
    }
}
