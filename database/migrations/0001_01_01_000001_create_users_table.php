<?php

use App\Support\Database\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users belong to exactly one agency. The same email can exist in two agencies as two separate accounts,
 * so one agency can never see or reset another agency's login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('locale', 5)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->primary(['tenant_id', 'email']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // Not tenant-scoped: the session cookie is host-only, and the user it points to is still
        // loaded through the tenant scope + RLS, so a session can't cross agencies.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        foreach (['users', 'password_reset_tokens'] as $t) {
            RowLevelSecurity::protect($t);
            RowLevelSecurity::grantToApp($t);
            RowLevelSecurity::grantToPlatform($t);
        }
        RowLevelSecurity::grantToApp('sessions');
        RowLevelSecurity::grantToPlatform('sessions');
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
