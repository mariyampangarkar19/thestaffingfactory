<?php

namespace App\Actions\Platform;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use LogicException;

/**
 * Creates an agency with its default sub-domain and first owner account.
 * Runs on the pgsql_platform connection (super-admin panel or console).
 */
final class ProvisionTenant
{
    /**
     * @param  array{slug: string, name: string, country_code?: string, default_locale?: string, owner_name: string, owner_email: string}  $data
     */
    public function handle(array $data, bool $sendInvite = true): Tenant
    {
        if (DB::getDefaultConnection() !== 'pgsql_platform') {
            throw new LogicException('Agencies can only be created from the platform connection.');
        }

        $tenant = DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create([
                'slug' => Str::lower($data['slug']),
                'name' => $data['name'],
                'country_code' => strtoupper($data['country_code'] ?? 'CM'),
                'default_locale' => $data['default_locale'] ?? 'fr',
                'settings' => ['country_packs' => [strtoupper($data['country_code'] ?? 'CM')]],
            ]);

            $tenant->domains()->create([
                'domain' => $tenant->slug.'.'.config('platform.base_domain'),
                'is_primary' => true,
                'verified_at' => now(),
            ]);

            $owner = new User(['name' => $data['owner_name'], 'email' => Str::lower($data['owner_email'])]);
            $owner->forceFill(['tenant_id' => $tenant->id, 'password' => Str::password(40)])->save();

            setPermissionsTeamId($tenant->id);
            $owner->assignRole(Role::Owner->value);
            setPermissionsTeamId(null);

            return $tenant;
        });

        if ($sendInvite) {
            // The owner sets their own password through the reset link; we never email passwords.
            // Sent from inside the agency so the token row gets the right tenant_id.
            DB::setDefaultConnection('pgsql');
            try {
                app(TenantContext::class)->clear();
                app(TenantContext::class)->run($tenant, fn () => Password::broker()->sendResetLink(['email' => Str::lower($data['owner_email'])]));
            } finally {
                DB::setDefaultConnection('pgsql_platform');
                app(TenantContext::class)->enterPlatformMode();
            }
        }

        return $tenant;
    }
}
