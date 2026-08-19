<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_templates', function (Blueprint $table) {
            $table->enum('category', [
                'property-collateral',
                'property-auction',
                'property-rental',
            ])->nullable()->after('purpose');
            $table->index('category', 'offer_templates_category_idx');
        });

        Schema::table('offer_template_versions', function (Blueprint $table) {
            $table->date('effective_until')->nullable()->after('effective_from');
            $this->addReviewColumns($table, 'offer_template_version');
        });

        Schema::table('issuer_profile_versions', function (Blueprint $table) {
            $table->unsignedInteger('letterhead_width_px')->nullable()->after('letterhead_mime');
            $table->unsignedInteger('letterhead_height_px')->nullable()->after('letterhead_width_px');
            $table->unsignedBigInteger('letterhead_size_bytes')->nullable()->after('letterhead_height_px');
            $this->addReviewColumns($table, 'issuer_profile_version');
        });

        Schema::table('document_signer_versions', function (Blueprint $table) {
            $this->addReviewColumns($table, 'document_signer_version');
        });

        Schema::table('offer_engagements', function (Blueprint $table) {
            $table->enum('fee_presentation', ['lump_sum', 'per_asset'])
                ->default('lump_sum')
                ->after('tax_inclusion');
        });

        Schema::table('offer_assets', function (Blueprint $table) {
            $table->unsignedBigInteger('exposure_amount')->nullable()->after('inspection_note');
            $table->unsignedBigInteger('reference_market_value')->nullable()->after('exposure_amount');
            $table->unsignedBigInteger('reference_liquidation_value')->nullable()->after('reference_market_value');
            $table->unsignedSmallInteger('liquidation_discount_bps')->nullable()->after('reference_liquidation_value');
        });
    }

    public function down(): void
    {
        Schema::table('offer_assets', function (Blueprint $table) {
            $table->dropColumn([
                'exposure_amount',
                'reference_market_value',
                'reference_liquidation_value',
                'liquidation_discount_bps',
            ]);
        });

        Schema::table('offer_engagements', function (Blueprint $table) {
            $table->dropColumn('fee_presentation');
        });

        Schema::table('document_signer_versions', function (Blueprint $table) {
            $this->dropReviewColumns($table, 'document_signer_version');
        });

        Schema::table('issuer_profile_versions', function (Blueprint $table) {
            $this->dropReviewColumns($table, 'issuer_profile_version');
            $table->dropColumn([
                'letterhead_width_px',
                'letterhead_height_px',
                'letterhead_size_bytes',
            ]);
        });

        Schema::table('offer_template_versions', function (Blueprint $table) {
            $this->dropReviewColumns($table, 'offer_template_version');
            $table->dropColumn('effective_until');
        });

        Schema::table('offer_templates', function (Blueprint $table) {
            $table->dropIndex('offer_templates_category_idx');
            $table->dropColumn('category');
        });
    }

    private function addReviewColumns(Blueprint $table, string $prefix): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('submitted_at')->nullable();
        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('rejection_note')->nullable();

        $table->index(['status', 'submitted_at'], "{$prefix}_review_queue_idx");
        $table->index('created_by', "{$prefix}_creator_idx");
        $table->index('submitted_by', "{$prefix}_submitter_idx");
        $table->index('reviewed_by', "{$prefix}_reviewer_idx");
    }

    private function dropReviewColumns(Blueprint $table, string $prefix): void
    {
        $table->dropForeign(['created_by']);
        $table->dropForeign(['submitted_by']);
        $table->dropForeign(['reviewed_by']);
        $table->dropIndex("{$prefix}_review_queue_idx");
        $table->dropIndex("{$prefix}_creator_idx");
        $table->dropIndex("{$prefix}_submitter_idx");
        $table->dropIndex("{$prefix}_reviewer_idx");
        $table->dropColumn([
            'created_by',
            'submitted_by',
            'submitted_at',
            'reviewed_by',
            'reviewed_at',
            'rejection_note',
        ]);
    }
};
