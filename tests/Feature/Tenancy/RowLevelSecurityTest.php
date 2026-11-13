<?php

namespace Tests\Feature\Tenancy;

use App\Console\Commands\CheckDatabaseSecurity;
use App\Models\FreelancerProfile;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Database-level isolation: these use raw queries (no Eloquent scope) to prove Postgres itself blocks cross-agency access.
 */
class RowLevelSecurityTest extends TestCase
{
    public function test_every_table_with_tenant_id_has_forced_row_level_security(): void
    {
        $this->assertSame([], CheckDatabaseSecurity::unprotectedTenantTables());
    }

    public function test_database_security_check_passes(): void
    {
        $this->artisan('platform:check-db')->assertSuccessful();
    }

    public function test_raw_queries_only_see_the_current_agency(): void
    {
        [$a, $b] = [$this->createTenant('alpha'), $this->createTenant('beta')];
        $ctx = app(TenantContext::class);

        $ctx->run($a, fn () => FreelancerProfile::query()->create(['first_name' => 'Awa', 'last_name' => 'A']));
        $ctx->run($b, fn () => FreelancerProfile::query()->create(['first_name' => 'Bello', 'last_name' => 'B']));

        $names = $ctx->run($a, fn () => DB::table('freelancer_profiles')->pluck('first_name')->all());
        $this->assertSame(['Awa'], $names);
    }

    public function test_no_agency_set_means_no_rows(): void
    {
        $a = $this->createTenant('alpha');
        app(TenantContext::class)->run($a, fn () => FreelancerProfile::query()->create(['first_name' => 'Awa', 'last_name' => 'A']));

        $this->assertSame(0, DB::table('freelancer_profiles')->count());
    }

    public function test_cannot_insert_rows_for_another_agency(): void
    {
        [$a, $b] = [$this->createTenant('alpha'), $this->createTenant('beta')];

        $this->expectException(QueryException::class);

        app(TenantContext::class)->run($a, fn () => DB::table('freelancer_profiles')->insert([
            'id' => (string) Str::uuid7(), 'tenant_id' => $b->id, 'first_name' => 'X', 'last_name' => 'Y',
        ]));
    }

    public function test_cannot_move_rows_to_another_agency_with_raw_update(): void
    {
        [$a, $b] = [$this->createTenant('alpha'), $this->createTenant('beta')];
        $ctx = app(TenantContext::class);
        $ctx->run($a, fn () => FreelancerProfile::query()->create(['first_name' => 'Awa', 'last_name' => 'A']));

        $this->expectException(QueryException::class);
        $ctx->run($a, fn () => DB::table('freelancer_profiles')->update(['tenant_id' => $b->id]));
    }

    public function test_audit_log_is_append_only(): void
    {
        $a = $this->createTenant('alpha');
        $ctx = app(TenantContext::class);
        $ctx->run($a, fn () => DB::table('audit_logs')->insert(['action' => 'test']));

        $this->expectException(QueryException::class);
        $ctx->run($a, fn () => DB::table('audit_logs')->update(['action' => 'tampered']));
    }

    public function test_app_role_cannot_read_platform_admins_or_write_tenants(): void
    {
        $this->expectException(QueryException::class);
        DB::table('platform_admins')->count();
    }

    public function test_app_role_cannot_create_agencies(): void
    {
        $this->expectException(QueryException::class);
        DB::table('tenants')->insert(['id' => (string) Str::uuid7(), 'slug' => 'evil', 'name' => 'Evil']);
    }
}
