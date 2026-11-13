<?php

use App\Support\Database\RowLevelSecurity as Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: the app can insert and read, never change or delete.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type', 40)->nullable(); // user | platform_admin | integration | system
            $table->uuid('actor_id')->nullable();
            $table->string('action', 80); // freelancer.approved, timesheet.adjusted, auth.login...
            $table->string('subject_type', 80)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('meta')->default('{}'); // changed field NAMES, never personal values
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
        });

        // Keys for server-to-server calls, e.g. an agency's website sending form submissions.
        Schema::create('integration_keys', function (Blueprint $table) {
            $table->uuid('id')->primary(); // sent as X-Key-Id, not secret
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('secret'); // encrypted at rest
            $table->jsonb('scopes')->default('[]'); // ["intake:freelancers","intake:requests"]
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // Receipt for every website submission, so nothing is lost if processing fails. Payload is purged after 30 days.
        Schema::create('intake_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('integration_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30);
            $table->string('external_ref', 100)->nullable();
            $table->string('status', 20)->default('received');
            $table->text('payload')->nullable(); // encrypted at rest
            $table->string('error')->nullable();
            $table->nullableUuidMorphs('result'); // the freelancer or request it created
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'kind', 'status']);
        });
        Rls::allowOnly('intake_submissions', 'kind', ['freelancer', 'staff_request']);
        Rls::allowOnly('intake_submissions', 'status', ['received', 'processed', 'failed']);

        Rls::protect('audit_logs');
        Rls::grantToApp('audit_logs', 'SELECT, INSERT');
        Rls::grantToPlatform('audit_logs', 'SELECT, INSERT');

        Rls::protect('integration_keys');
        Rls::grantToApp('integration_keys', 'SELECT, UPDATE'); // UPDATE for last_used_at; keys are issued by the platform side
        Rls::grantToPlatform('integration_keys');

        Rls::tenantTable('intake_submissions');
        Rls::grantSequenceUsage();
    }

    public function down(): void
    {
        foreach (['intake_submissions', 'integration_keys', 'audit_logs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
