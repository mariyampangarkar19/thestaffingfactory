<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

/**
 * Role permissions for Note. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class NotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notes.view');
    }

    public function view(User $user, Note $record): bool
    {
        return $user->can('notes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('notes.manage');
    }

    public function update(User $user, Note $record): bool
    {
        return $user->can('notes.manage');
    }

    public function delete(User $user, Note $record): bool
    {
        return $user->can('notes.manage');
    }

    public function restore(User $user, Note $record): bool
    {
        return $user->can('notes.manage');
    }

    public function forceDelete(User $user, Note $record): bool
    {
        return false;
    }
}
