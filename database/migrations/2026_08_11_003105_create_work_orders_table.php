<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->string('contract_no')->unique();
            $table->date('contract_date')->nullable();
            $table->boolean('survey_required')->default(true);
            $table->date('sla_date')->nullable();
            $table->string('current_status')->default('PERSIAPAN'); // PERSIAPAN, SURVEY, PENGERJAAN, REVIEW, CETAK, SELESAI, BATAL
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
