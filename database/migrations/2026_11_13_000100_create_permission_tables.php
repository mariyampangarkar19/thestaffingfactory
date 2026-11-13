<?php

use App\Support\Database\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission tables, adapted for UUID users and "teams" = agencies (tenant_id).
 * Role and permission definitions are global (tenant_id NULL) and read-only for the web app.
 * Role assignments (model_has_roles / model_has_permissions) are tenant-owned and protected by RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable()->index(); // NULL = built-in role every agency can use
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['tenant_id', 'name', 'guard_name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->uuid('tenant_id')->index();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['model_id', 'model_type']);
            $table->primary(['tenant_id', 'permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->uuid('tenant_id')->index();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['model_id', 'model_type']);
            $table->primary(['tenant_id', 'role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        foreach (['model_has_permissions', 'model_has_roles'] as $t) {
            RowLevelSecurity::protect($t);
            RowLevelSecurity::grantToApp($t);
            RowLevelSecurity::grantToPlatform($t);
        }

        // Roles: an agency sees the built-in roles plus (later) its own custom roles.
        $current = RowLevelSecurity::CURRENT_TENANT;
        DB::statement('ALTER TABLE roles ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE roles FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_or_global ON roles USING (tenant_id IS NULL OR tenant_id = {$current}) WITH CHECK (tenant_id = {$current})");

        foreach (['permissions', 'roles', 'role_has_permissions'] as $t) {
            RowLevelSecurity::grantToApp($t, 'SELECT');
            RowLevelSecurity::grantToPlatform($t);
        }
    }

    public function down(): void
    {
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
