<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_number_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 64);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['scope_key', 'sequence_year'], 'offer_num_counter_scope_year_uq');
        });

        Schema::create('offer_number_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('scope_key', 64);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedInteger('sequence_no');
            $table->string('number_suffix', 16)->default('');
            $table->json('format_snapshot');
            $table->string('full_number')->unique();
            $table->string('status', 20)->default('allocated');
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['scope_key', 'sequence_year', 'sequence_no', 'number_suffix'],
                'offer_num_alloc_scope_year_seq_suffix_uq'
            );
            $table->unique(['offer_id', 'active_slot'], 'offer_num_alloc_active_offer_uq');
            $table->index(['offer_id', 'status'], 'offer_num_alloc_offer_status_idx');
            $table->index('branch_id', 'offer_num_alloc_branch_idx');
            $table->index('allocated_by', 'offer_num_alloc_allocated_by_idx');
            $table->index('voided_by', 'offer_num_alloc_voided_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_number_allocations');
        Schema::dropIfExists('offer_number_counters');
    }
};
