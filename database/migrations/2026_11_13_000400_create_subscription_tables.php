<?php

use App\Support\Database\RowLevelSecurity as Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin: plans, agency subscriptions and recorded payments.
 * (Quote section 3 "Subscription Management" and section 5.)
 * An agency can READ its own subscription (to show "expires on..."); only the platform side can change it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('billing_period', 20)->default('monthly');
            $table->jsonb('limits')->default('{}'); // {"staff_users":5,"freelancers":500,"storage_gb":10}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Rls::allowOnly('plans', 'billing_period', ['monthly', 'quarterly', 'yearly']);

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->date('starts_on');
            $table->date('ends_on'); // access is restricted after this date (plus grace_days)
            $table->unsignedSmallInteger('grace_days')->default(7);
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'ends_on']);
        });
        Rls::allowOnly('subscriptions', 'status', ['trial', 'active', 'expired', 'suspended', 'cancelled']);

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->date('paid_on');
            $table->string('method', 30)->nullable();
            $table->string('transaction_ref')->nullable();
            $table->date('covers_from')->nullable();
            $table->date('covers_to')->nullable();
            $table->uuid('recorded_by')->nullable(); // platform_admins.id
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'paid_on']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->jsonb('value');
            $table->timestamps();
        });

        Rls::grantToApp('plans', 'SELECT');
        Rls::grantToPlatform('plans');
        foreach (['subscriptions', 'subscription_payments'] as $t) {
            Rls::protect($t);
            Rls::grantToApp($t, 'SELECT');
            Rls::grantToPlatform($t);
        }
        Rls::grantToPlatform('platform_settings');
    }

    public function down(): void
    {
        foreach (['platform_settings', 'subscription_payments', 'subscriptions', 'plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
