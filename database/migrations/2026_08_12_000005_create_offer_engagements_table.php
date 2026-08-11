<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->unique()->constrained('offers')->restrictOnDelete();
            $table->string('workflow_state', 24)->default('data_draft');
            $table->foreignId('current_review_version_id')
                ->nullable()
                ->constrained('offer_document_versions')
                ->restrictOnDelete();
            $table->foreignId('current_final_version_id')
                ->nullable()
                ->constrained('offer_document_versions')
                ->restrictOnDelete();
            $table->foreignId('state_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('state_changed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('template_version_id')
                ->nullable()
                ->constrained('offer_template_versions')
                ->restrictOnDelete();
            $table->foreignId('issuer_profile_version_id')
                ->nullable()
                ->constrained('issuer_profile_versions')
                ->restrictOnDelete();
            $table->foreignId('signer_version_id')
                ->nullable()
                ->constrained('document_signer_versions')
                ->restrictOnDelete();
            $table->string('issue_city')->nullable();
            $table->string('recipient_attention')->nullable();
            $table->string('recipient_organization')->nullable();
            $table->text('recipient_address')->nullable();
            $table->string('recipient_city')->nullable();
            $table->string('subject')->nullable();
            $table->string('request_reference_type', 20)->default('none');
            $table->string('request_reference_no')->nullable();
            $table->date('request_reference_date')->nullable();
            $table->text('opening_context')->nullable();
            $table->string('ownership_form')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->string('purpose')->nullable();
            $table->string('valuation_basis')->nullable();
            $table->date('valuation_date')->nullable();
            $table->string('valuation_date_rule')->nullable();
            $table->string('investigation_level')->nullable();
            $table->string('report_format')->nullable();
            $table->string('report_language', 16)->default('id');
            $table->unsignedSmallInteger('report_copies')->nullable();
            $table->unsignedSmallInteger('completion_days')->nullable();
            $table->string('completion_day_type', 16)->nullable();
            $table->string('tax_inclusion', 20)->nullable();
            $table->unsignedSmallInteger('ppn_rate_bps')->nullable();
            $table->unsignedSmallInteger('pph_rate_bps')->nullable();
            $table->json('cost_inclusions')->nullable();
            $table->text('special_assumptions')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();

            $table->index('workflow_state', 'offer_engagement_workflow_state_idx');
            $table->index('current_review_version_id', 'offer_engagement_review_version_idx');
            $table->index('current_final_version_id', 'offer_engagement_final_version_idx');
            $table->index('state_changed_by', 'offer_engagement_state_actor_idx');
            $table->index('template_version_id', 'offer_engagement_template_version_idx');
            $table->index('issuer_profile_version_id', 'offer_engagement_issuer_version_idx');
            $table->index('signer_version_id', 'offer_engagement_signer_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_engagements');
    }
};
