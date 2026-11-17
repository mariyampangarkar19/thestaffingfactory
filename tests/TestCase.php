<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tests run against real Postgres with the same three roles as production, so row-level security is exercised.
 * The schema is built once per run as the owner; each test then empties the data tables.
 */
abstract class TestCase extends BaseTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if (! self::$migrated) {
            Artisan::call('migrate:fresh', ['--database' => 'pgsql_owner', '--force' => true]);
            $this->seed(RolesAndPermissionsSeeder::class);
            self::$migrated = true;
        }

        // DELETE (not TRUNCATE ... CASCADE) so the built-in roles, which reference tenants, survive.
        $owner = DB::connection('pgsql_owner');
        $owner->statement('TRUNCATE platform_admins, sessions, cache, cache_locks, jobs, failed_jobs');
        $owner->table('tenants')->delete();
        app(TenantContext::class)->clear();
    }

    protected function createTenant(string $slug = 'tsf', string $status = 'active'): Tenant
    {
        $tenant = Tenant::on('pgsql_platform')->create([
            'slug' => $slug, 'name' => Str::title($slug), 'status' => $status,
        ]);
        $tenant->domains()->create(['domain' => "{$slug}.staffing.test", 'is_primary' => true, 'verified_at' => now()]);

        return Tenant::query()->findOrFail($tenant->id);
    }

    protected function createUser(Tenant $tenant, string $role, array $attributes = []): User
    {
        return app(TenantContext::class)->run($tenant, function () use ($role, $attributes) {
            $user = User::query()->create([
                'name' => 'Test '.$role,
                'email' => $role.'-'.Str::random(6).'@example.com',
                'password' => 'password-123',
                ...$attributes,
            ]);
            $user->assignRole($role);

            return $user;
        });
    }

    protected function tenantUrl(Tenant $tenant, string $path): string
    {
        return "http://{$tenant->slug}.staffing.test/".ltrim($path, '/');
    }
}
