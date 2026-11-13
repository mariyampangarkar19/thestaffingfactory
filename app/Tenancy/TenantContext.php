<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Holds the agency (tenant) for the current request or job.
 *
 * Isolation is enforced twice:
 *  1. In PHP: every tenant-owned model has a global scope on tenant_id (BelongsToTenant).
 *  2. In Postgres: row-level security compares tenant_id with the app.tenant_id setting written here.
 * If either layer is missing or wrong, the other still blocks cross-agency reads and writes.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $platformMode = false;

    public function set(Tenant $tenant): void
    {
        if ($this->platformMode) {
            throw new LogicException('Cannot enter a tenant while in platform mode.');
        }

        $this->tenant = $tenant;
        DB::connection()->statement("select set_config('app.tenant_id', ?, false)", [$tenant->getKey()]);
        setPermissionsTeamId($tenant->getKey());
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->platformMode = false;
        DB::connection()->statement("select set_config('app.tenant_id', '', false)");
        setPermissionsTeamId(null);
    }

    /**
     * Super-admin only. The caller must already be on the pgsql_platform connection.
     */
    public function enterPlatformMode(): void
    {
        if (DB::getDefaultConnection() !== 'pgsql_platform') {
            throw new LogicException('Platform mode requires the pgsql_platform connection.');
        }

        $this->tenant = null;
        $this->platformMode = true;
    }

    /**
     * Run a callback inside one tenant, then restore the previous state.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $previous ? $this->set($previous) : $this->clear();
        }
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function isPlatformMode(): bool
    {
        return $this->platformMode;
    }

    public function require(): Tenant
    {
        return $this->tenant ?? throw new MissingTenantException;
    }
}
