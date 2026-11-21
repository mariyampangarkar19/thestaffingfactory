<?php

namespace App\Dashboard\Contracts;

use App\Dashboard\AttentionItem;
use App\Models\User;

/**
 * A question the dashboard asks every time it opens ("are there timesheets to approve?").
 * Return null when there is nothing to do or the person may not act on it.
 * Register new checks in config/platform.php under 'dashboard_checks'.
 */
interface AttentionCheck
{
    public function check(User $user): ?AttentionItem;
}
