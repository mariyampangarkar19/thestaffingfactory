<?php

namespace Database\Seeders;

use App\Enums\Role as RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Built-in roles shared by every agency (tenant_id NULL). Safe to run again after adding permissions.
 * Runs on the platform connection because the web app role can only read roles.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    public const MATRIX = [
        'freelancers.view' => ['owner', 'manager', 'recruiter', 'sales', 'viewer'],
        'freelancers.manage' => ['owner', 'manager', 'recruiter'],
        'documents.review' => ['owner', 'manager', 'recruiter'],
        'clients.view' => ['owner', 'manager', 'sales', 'finance', 'viewer'],
        'clients.manage' => ['owner', 'manager', 'sales'],
        'requests.view' => ['owner', 'manager', 'recruiter', 'sales', 'viewer'],
        'requests.manage' => ['owner', 'manager', 'sales'],
        'assignments.manage' => ['owner', 'manager', 'recruiter'],
        'finance.view' => ['owner', 'finance'],
        'finance.manage' => ['owner', 'finance'],
        'users.manage' => ['owner'],
        'settings.manage' => ['owner'],
        'audit.view' => ['owner'],
        'portal.use' => ['freelancer'],
    ];

    public function run(): void
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_platform');

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            setPermissionsTeamId(null);

            foreach (RoleName::cases() as $role) {
                Role::query()->firstOrCreate(['name' => $role->value, 'guard_name' => 'web', 'tenant_id' => null]);
            }

            foreach (self::MATRIX as $permission => $roles) {
                Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            }

            foreach (RoleName::cases() as $roleName) {
                $role = Role::query()->whereNull('tenant_id')->where('name', $roleName->value)->firstOrFail();
                $role->syncPermissions(array_keys(array_filter(self::MATRIX, fn (array $roles) => in_array($roleName->value, $roles, true))));
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }
}
