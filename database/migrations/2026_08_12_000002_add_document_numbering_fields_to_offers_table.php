<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedSmallInteger('sequence_year')->nullable()->after('sequence_no');
            $table->string('number_suffix', 16)->default('')->after('sequence_year');
            $table->foreignId('current_number_allocation_id')
                ->nullable()
                ->after('number_suffix')
                ->constrained('offer_number_allocations')
                ->restrictOnDelete();

            $table->unique('current_number_allocation_id', 'offers_current_num_alloc_uq');
            $table->index(['sequence_year', 'sequence_no'], 'offers_year_sequence_idx');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropUnique('offers_current_num_alloc_uq');
            $table->dropIndex('offers_year_sequence_idx');
            $table->dropConstrainedForeignId('current_number_allocation_id');
            $table->dropColumn(['sequence_year', 'number_suffix']);
        });
    }
};
