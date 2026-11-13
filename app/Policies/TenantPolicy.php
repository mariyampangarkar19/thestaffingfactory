<?php

namespace App\Policies;

use App\Models\PlatformAdmin;
use App\Models\Tenant;

class TenantPolicy
{
    public function viewAny(PlatformAdmin $admin): bool
    {
        return true;
    }

    public function view(PlatformAdmin $admin, Tenant $tenant): bool
    {
        return true;
    }

    public function create(PlatformAdmin $admin): bool
    {
        return true;
    }

    public function update(PlatformAdmin $admin, Tenant $tenant): bool
    {
        return true;
    }

    public function delete(PlatformAdmin $admin, Tenant $tenant): bool
    {
        return false;
    }
}
