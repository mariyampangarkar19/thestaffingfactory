<?php

use App\Support\Database\RowLevelSecurity as Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business customers (hotels, restaurants, event organisers). CRM records only: customers never log in.
 * (Quote section 1 "Customer Management".)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('sector', 60)->nullable(); // hotel, restaurant, events, retail...
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->char('country_code', 2)->default('CM');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->string('status', 20)->default('lead');
            $table->string('source', 60)->nullable();
            $table->string('external_ref', 100)->nullable();
            $table->foreignUuid('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'external_ref']);
            $table->index(['tenant_id', 'status']);
        });
        Rls::allowOnly('clients', 'status', ['lead', 'active', 'inactive']);

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'client_id']);
        });

        Rls::tenantTable('clients');
        Rls::tenantTable('client_contacts');
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('clients');
    }
};
