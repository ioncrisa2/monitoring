<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('version_state', 20)->default('in_review');
            $table->foreignId('template_version_id')->constrained('offer_template_versions')->restrictOnDelete();
            $table->foreignId('issuer_profile_version_id')->constrained('issuer_profile_versions')->restrictOnDelete();
            $table->foreignId('signer_version_id')->nullable()->constrained('document_signer_versions')->restrictOnDelete();
            $table->json('data_snapshot');
            $table->char('snapshot_sha256', 64);
            $table->char('approved_snapshot_sha256', 64)->nullable();
            $table->char('approved_render_profile_hash', 64)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('supersedes_id')
                ->nullable()
                ->constrained('offer_document_versions')
                ->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['offer_id', 'version_no'], 'offer_document_offer_version_uq');
            $table->index(['offer_id', 'version_state'], 'offer_document_offer_state_idx');
            $table->index('template_version_id', 'offer_document_template_version_idx');
            $table->index('issuer_profile_version_id', 'offer_document_issuer_version_idx');
            $table->index('signer_version_id', 'offer_document_signer_version_idx');
            $table->index('submitted_by', 'offer_document_submitter_idx');
            $table->index('approved_by', 'offer_document_approver_idx');
            $table->index('finalized_by', 'offer_document_finalizer_idx');
            $table->index('supersedes_id', 'offer_document_supersedes_idx');
        });

        Schema::create('offer_document_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_document_version_id')
                ->constrained('offer_document_versions')
                ->restrictOnDelete();
            $table->string('artifact_type', 20);
            $table->unsignedInteger('artifact_no');
            $table->unsignedTinyInteger('final_slot')->nullable();
            $table->string('storage_status', 20)->default('pending');
            $table->string('generation_key', 128)->unique();
            $table->foreignId('source_draft_artifact_id')
                ->nullable()
                ->constrained('offer_document_artifacts')
                ->restrictOnDelete();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('renderer_version')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['offer_document_version_id', 'artifact_type', 'artifact_no'],
                'offer_artifact_version_type_no_uq'
            );
            $table->unique(
                ['offer_document_version_id', 'final_slot'],
                'offer_artifact_version_final_slot_uq'
            );
            $table->index(
                ['offer_document_version_id', 'storage_status'],
                'offer_artifact_version_storage_idx'
            );
            $table->index('source_draft_artifact_id', 'offer_artifact_source_draft_idx');
            $table->index('generated_by', 'offer_artifact_generator_idx');
        });

        Schema::table('offer_document_versions', function (Blueprint $table) {
            $table->foreignId('approved_draft_artifact_id')
                ->nullable()
                ->after('approved_snapshot_sha256')
                ->constrained('offer_document_artifacts')
                ->restrictOnDelete();
            $table->index('approved_draft_artifact_id', 'offer_document_approved_draft_idx');
        });
    }

    public function down(): void
    {
        Schema::table('offer_document_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_draft_artifact_id');
        });

        Schema::dropIfExists('offer_document_artifacts');
        Schema::dropIfExists('offer_document_versions');
    }
};
