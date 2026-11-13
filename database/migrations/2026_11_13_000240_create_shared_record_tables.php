<?php

use App\Support\Database\RowLevelSecurity as Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that attach to customers, freelancers, requests and assignments:
 * documents, notes/communication history, announcements and in-app notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('documentable'); // freelancer_profile | client | staff_request | assignment | payment
            $table->string('type', 40);
            $table->string('title')->nullable();
            $table->string('disk', 20)->default('private');
            $table->string('path'); // never public; served through short-lived signed links after a permission check
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64); // tamper evidence
            $table->string('status', 20)->default('pending');
            $table->date('expires_on')->nullable(); // certificates, IDs
            $table->boolean('visible_to_freelancer')->default(false);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'type']);
        });
        Rls::allowOnly('documents', 'type', ['cv', 'photo', 'intro_video', 'id_document', 'certificate', 'contract', 'invoice', 'payment_slip', 'other']);
        Rls::allowOnly('documents', 'status', ['pending', 'approved', 'rejected', 'expired']);

        // Internal notes and the log of calls/emails with customers and freelancers.
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('notable'); // client | freelancer_profile | staff_request | assignment
            $table->string('kind', 20)->default('note');
            $table->text('body');
            $table->timestamp('occurred_at')->nullable(); // when the call/email happened
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Rls::allowOnly('notes', 'kind', ['note', 'call', 'email', 'whatsapp', 'meeting']);

        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title_en')->nullable();
            $table->string('title_fr')->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_fr')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'published_at']);
        });

        // Laravel's notifications table, made agency-owned.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        foreach (['documents', 'notes', 'announcements', 'notifications'] as $t) {
            Rls::tenantTable($t);
        }
    }

    public function down(): void
    {
        foreach (['notifications', 'announcements', 'notes', 'documents'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
