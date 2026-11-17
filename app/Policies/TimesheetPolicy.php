<?php

namespace App\Policies;

use App\Models\Timesheet;
use App\Models\User;

/**
 * Role permissions for Timesheet. Agency scoping is already guaranteed by the tenant scope and row-level security.
 */
class TimesheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('timesheets.view');
    }

    public function view(User $user, Timesheet $record): bool
    {
        return $user->can('timesheets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('timesheets.review');
    }

    public function update(User $user, Timesheet $record): bool
    {
        return $user->can('timesheets.review');
    }

    public function delete(User $user, Timesheet $record): bool
    {
        return false;
    }

    public function restore(User $user, Timesheet $record): bool
    {
        return false;
    }

    public function forceDelete(User $user, Timesheet $record): bool
    {
        return false;
    }
}
