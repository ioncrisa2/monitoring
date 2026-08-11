<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('debtor_id')->nullable()->constrained('debtors')->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('identifier_snapshot')->nullable();
            $table->text('address_snapshot')->nullable();
            $table->unsignedTinyInteger('primary_slot')->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['offer_id', 'sort_order'], 'offer_subject_offer_sort_uq');
            $table->unique(['offer_id', 'primary_slot'], 'offer_subject_offer_primary_uq');
            $table->index('debtor_id', 'offer_subject_debtor_idx');
        });

        Schema::create('offer_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_subject_id')->constrained('offer_subjects')->cascadeOnDelete();
            $table->string('asset_type');
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->decimal('land_area_m2', 15, 2)->nullable();
            $table->decimal('building_area_m2', 15, 2)->nullable();
            $table->text('inspection_note')->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['offer_subject_id', 'sort_order'], 'offer_asset_subject_sort_uq');
        });

        Schema::create('offer_asset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_asset_id')->constrained('offer_assets')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_no');
            $table->date('issued_at')->nullable();
            $table->string('issuer')->nullable();
            $table->unsignedTinyInteger('primary_slot')->nullable();
            $table->unsignedInteger('sort_order');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(
                ['offer_asset_id', 'document_type', 'document_no'],
                'offer_asset_doc_type_number_uq'
            );
            $table->unique(['offer_asset_id', 'primary_slot'], 'offer_asset_doc_primary_uq');
            $table->unique(['offer_asset_id', 'sort_order'], 'offer_asset_doc_sort_uq');
        });

        Schema::create('offer_fee_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('offer_subject_id')->nullable()->constrained('offer_subjects')->nullOnDelete();
            $table->foreignId('offer_asset_id')->nullable()->constrained('offer_assets')->nullOnDelete();
            $table->string('label');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['offer_id', 'sort_order'], 'offer_fee_item_offer_sort_uq');
            $table->index('offer_subject_id', 'offer_fee_item_subject_idx');
            $table->index('offer_asset_id', 'offer_fee_item_asset_idx');
        });

        Schema::create('offer_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->unsignedSmallInteger('percentage_bps');
            $table->string('trigger_text');
            $table->unsignedSmallInteger('due_days')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'sequence'], 'offer_payment_term_offer_sequence_uq');
        });

        Schema::create('offer_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->string('requirement_code', 64)->nullable();
            $table->text('description_snapshot');
            $table->string('emphasis_style', 16)->default('normal');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['offer_id', 'sort_order'], 'offer_requirement_offer_sort_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_requirements');
        Schema::dropIfExists('offer_payment_terms');
        Schema::dropIfExists('offer_fee_items');
        Schema::dropIfExists('offer_asset_documents');
        Schema::dropIfExists('offer_assets');
        Schema::dropIfExists('offer_subjects');
    }
};
