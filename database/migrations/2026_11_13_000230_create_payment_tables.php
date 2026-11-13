<?php

use App\Support\Database\RowLevelSecurity as Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freelancer earnings and payment tracking. The platform records payments; it does not move money.
 * (Quote section 1 "Timesheets & Payment Management", section 2 "Payments".)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Default hourly rate per position; a client-specific row overrides the agency default.
        Schema::create('pay_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_role_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('hourly_rate', 12, 2);
            $table->char('currency', 3)->default('XAF');
            $table->date('effective_from');
            $table->timestamps();
            $table->index(['tenant_id', 'job_role_id', 'effective_from']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_profile_id')->constrained()->restrictOnDelete();
            $table->string('reference', 30); // e.g. PAY-2026-0001
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('XAF');
            $table->string('status', 20)->default('pending');
            $table->string('method', 30)->nullable(); // mobile_money, bank_transfer, cash...
            $table->string('transaction_ref')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'freelancer_profile_id']);
        });
        Rls::allowOnly('payments', 'status', ['pending', 'paid', 'cancelled']);

        // Which approved timesheets a payment covers. A timesheet can be paid only once.
        Schema::create('payment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('timesheet_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('minutes');
            $table->decimal('hourly_rate', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique('timesheet_id');
            $table->index(['tenant_id', 'payment_id']);
        });

        foreach (['pay_rates', 'payments', 'payment_items'] as $t) {
            Rls::tenantTable($t);
        }
    }

    public function down(): void
    {
        foreach (['payment_items', 'payments', 'pay_rates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
