<?php

use App\Support\Database\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level tables. These are not tenant-owned:
 *  - tenants / tenant_domains: the web app may only READ them (to find the agency from the host name).
 *  - platform_admins: the web app role gets NO access at all; only the super-admin connection can read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('REVOKE ALL ON SCHEMA public FROM PUBLIC');
        foreach (array_filter(config('platform.db_roles')) as $role) {
            if (DB::selectOne('select 1 as ok from pg_roles where rolname = ?', [$role])) {
                DB::statement('GRANT USAGE ON SCHEMA public TO "'.str_replace('"', '""', $role).'"');
            }
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 63)->unique();
            $table->string('name');
            $table->char('country_code', 2)->default('CM');
            $table->string('default_locale', 5)->default('fr');
            $table->string('timezone')->default('Africa/Douala');
            $table->string('status', 20)->default('active'); // active | suspended | closed
            $table->string('plan', 40)->default('starter');
            $table->jsonb('settings')->default('{}'); // branding, enabled country packs, feature flags
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique(); // lower-case host name, no port
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        foreach (['tenants', 'tenant_domains'] as $t) {
            RowLevelSecurity::grantToApp($t, 'SELECT');
            RowLevelSecurity::grantToPlatform($t);
        }
        RowLevelSecurity::grantToPlatform('platform_admins');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
