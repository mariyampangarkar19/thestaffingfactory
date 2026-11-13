<?php

use App\Support\Database\RowLevelSecurity as Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staffing requests -> positions needed -> shifts -> assignments -> timesheets.
 * (Quote section 1 "Staffing Requests", "Assignments & Scheduling", "Timesheets".)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('client_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 30); // human-readable, e.g. REQ-2026-0001
            $table->string('title');
            $table->text('details')->nullable(); // what the customer asked for, as submitted
            $table->string('location')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('new');
            $table->string('channel', 20)->default('manual'); // how it arrived
            $table->string('source', 60)->nullable();         // utm_source
            $table->string('external_ref', 100)->nullable();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // operator in charge
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference']);
            $table->unique(['tenant_id', 'external_ref']);
            $table->index(['tenant_id', 'status', 'starts_on']);
            $table->index(['tenant_id', 'assigned_to']);
        });
        Rls::allowOnly('staff_requests', 'status', ['new', 'in_progress', 'confirmed', 'completed', 'cancelled']);
        Rls::allowOnly('staff_requests', 'channel', ['website', 'manual', 'phone', 'email', 'whatsapp']);

        // One line per position requested: "4 waiters", "2 hostesses".
        Schema::create('staff_request_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('staff_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_role_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('pay_rate', 12, 2)->nullable(); // what the freelancer earns per hour for this request
            $table->char('currency', 3)->default('XAF');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'staff_request_id']);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('staff_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('staff_request_position_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->string('status', 20)->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'staff_request_id']);
        });
        Rls::allowOnly('shifts', 'status', ['planned', 'confirmed', 'completed', 'cancelled']);

        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_profile_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('assigned');
            $table->decimal('pay_rate', 12, 2)->nullable(); // copied from the position when assigned, so later rate changes don't rewrite history
            $table->char('currency', 3)->default('XAF');
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['shift_id', 'freelancer_profile_id']);
            $table->index(['tenant_id', 'freelancer_profile_id', 'status']);
        });
        Rls::allowOnly('assignments', 'status', ['assigned', 'confirmed', 'completed', 'cancelled', 'no_show']);

        Schema::table('freelancer_evaluations', function (Blueprint $table) {
            $table->foreign('assignment_id')->references('id')->on('assignments')->nullOnDelete();
        });

        // Hours worked: submitted by the freelancer, approved or adjusted by the agency.
        Schema::create('timesheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_profile_id')->constrained()->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedInteger('submitted_minutes');
            $table->unsignedInteger('approved_minutes')->nullable(); // differs from submitted when adjusted
            $table->string('status', 20)->default('submitted');
            $table->text('freelancer_note')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique('assignment_id'); // one timesheet per assignment
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'freelancer_profile_id']);
        });
        Rls::allowOnly('timesheets', 'status', ['submitted', 'approved', 'adjusted', 'rejected']);

        foreach (['staff_requests', 'staff_request_positions', 'shifts', 'assignments', 'timesheets'] as $t) {
            Rls::tenantTable($t);
        }
    }

    public function down(): void
    {
        Schema::table('freelancer_evaluations', fn (Blueprint $table) => $table->dropForeign(['assignment_id']));
        foreach (['timesheets', 'assignments', 'shifts', 'staff_request_positions', 'staff_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
