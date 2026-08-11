<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('purpose')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['active', 'is_default'], 'offer_templates_active_default_idx');
        });

        Schema::create('offer_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_template_id')->constrained('offer_templates')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('clause_schema');
            $table->json('condition_schema')->nullable();
            $table->string('layout_version', 64)->default('standard-v1');
            $table->string('header_mode', 24)->default('odd_pages');
            $table->string('status', 20)->default('draft');
            $table->date('effective_from')->nullable();
            $table->char('checksum', 64);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['offer_template_id', 'version_no'], 'offer_template_version_uq');
            $table->index(
                ['offer_template_id', 'status', 'effective_from'],
                'offer_template_version_status_idx'
            );
            $table->index('approved_by', 'offer_template_version_approver_idx');
        });

        Schema::create('issuer_profile_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('legal_name');
            $table->string('permit_no')->nullable();
            $table->string('office_label')->nullable();
            $table->text('address');
            $table->string('city');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->char('letterhead_sha256', 64)->nullable();
            $table->string('letterhead_mime', 100)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('status', 20)->default('draft');
            $table->char('checksum', 64);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'version_no'], 'issuer_profile_branch_version_uq');
            $table->index(['branch_id', 'status'], 'issuer_profile_branch_status_idx');
            $table->index('approved_by', 'issuer_profile_approver_idx');
        });

        Schema::create('document_signer_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('signer_key', 64);
            $table->unsignedInteger('version_no');
            $table->string('full_name');
            $table->string('title_suffix')->nullable();
            $table->string('position');
            $table->string('permit_no')->nullable();
            $table->string('registration_no')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('signature_path')->nullable();
            $table->char('signature_sha256', 64)->nullable();
            $table->string('signature_mime', 100)->nullable();
            $table->string('stamp_path')->nullable();
            $table->char('stamp_sha256', 64)->nullable();
            $table->string('stamp_mime', 100)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('status', 20)->default('draft');
            $table->char('checksum', 64);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['branch_id', 'signer_key', 'version_no'],
                'document_signer_branch_key_version_uq'
            );
            $table->index(['branch_id', 'status'], 'document_signer_branch_status_idx');
            $table->index('approved_by', 'document_signer_approver_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signer_versions');
        Schema::dropIfExists('issuer_profile_versions');
        Schema::dropIfExists('offer_template_versions');
        Schema::dropIfExists('offer_templates');
    }
};
