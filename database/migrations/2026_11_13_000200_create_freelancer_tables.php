<?php

use App\Support\Database\RowLevelSecurity as Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freelancers: profile, positions they can work, quiz results, availability, evaluations.
 * (Quote section 1 "Freelancer Management" and section 2 "My Profile".)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Positions an agency staffs (waiter, hostess, bartender...). Each agency keeps its own list.
        Schema::create('job_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 60);
            $table->string('name_en');
            $table->string('name_fr');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('freelancer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete(); // portal login, set on approval
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->char('country_code', 2)->default('CM');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->jsonb('languages')->default('[]');    // ["fr","en"]
            $table->text('experience_summary')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->jsonb('country_data')->default('{}'); // fields added by the agency's country pack
            $table->string('status', 20)->default('new');
            $table->string('availability_status', 20)->default('available');
            $table->string('source', 60)->nullable();        // utm_source from the website
            $table->string('external_ref', 100)->nullable(); // entry id in the WordPress plugin
            $table->timestamp('consented_at')->nullable();   // data-protection consent
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'external_ref']);
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'city']);
        });
        Rls::allowOnly('freelancer_profiles', 'status', ['new', 'screening', 'approved', 'rejected', 'inactive']);
        Rls::allowOnly('freelancer_profiles', 'availability_status', ['available', 'limited', 'unavailable']);

        Schema::create('freelancer_job_role', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_role_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->primary(['freelancer_profile_id', 'job_role_id']);
            $table->index(['tenant_id', 'job_role_id']);
        });

        Schema::create('quiz_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('quiz_role', 60);
            $table->unsignedTinyInteger('score');   // 0-100
            $table->string('grade', 2)->nullable(); // A | B | C, null when failed
            $table->boolean('passed');
            $table->string('locale', 5)->default('fr');
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->index(['tenant_id', 'freelancer_profile_id']);
        });

        // Days a freelancer is not available (set by the agency; by the freelancer too if the agency allows it).
        Schema::create('freelancer_unavailabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'freelancer_profile_id', 'starts_on']);
        });

        // Internal performance evaluations; never shown to the freelancer.
        Schema::create('freelancer_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('freelancer_profile_id')->constrained()->cascadeOnDelete();
            $table->uuid('assignment_id')->nullable()->index(); // FK added with the assignments table
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'freelancer_profile_id']);
        });

        foreach (['job_roles', 'freelancer_profiles', 'freelancer_job_role', 'quiz_results', 'freelancer_unavailabilities', 'freelancer_evaluations'] as $t) {
            Rls::tenantTable($t);
        }
    }

    public function down(): void
    {
        foreach (['freelancer_evaluations', 'freelancer_unavailabilities', 'quiz_results', 'freelancer_job_role', 'freelancer_profiles', 'job_roles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
