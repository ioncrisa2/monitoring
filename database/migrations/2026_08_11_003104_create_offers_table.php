<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_no')->unique();
            $table->date('offer_date');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('debtor_id')->constrained('debtors');
            $table->foreignId('client_id')->constrained('organizations');
            $table->foreignId('report_user_id')->nullable()->constrained('organizations');
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('ta', 15, 2)->default(0);
            $table->decimal('dpp', 15, 2)->default(0);
            $table->decimal('ppn', 15, 2)->default(0);
            $table->decimal('pph', 15, 2)->default(0);
            $table->string('outcome')->default('DRAFT'); // DRAFT, DIKIRIM, DITERIMA, TIDAK_LANJUT, DITOLAK
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
