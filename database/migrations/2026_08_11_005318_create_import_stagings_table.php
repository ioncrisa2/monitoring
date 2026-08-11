<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_stagings', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id');
            $table->string('branch_code')->nullable();
            $table->string('offer_no');
            $table->date('contract_date')->nullable();
            $table->string('debtor_name')->nullable();
            $table->string('client_name')->nullable();
            $table->string('report_user_name')->nullable();
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('ta', 15, 2)->default(0);
            $table->string('status')->default('SELESAI');
            $table->string('report_no')->nullable();
            $table->date('report_date')->nullable();
            $table->decimal('resume_value', 15, 2)->default(0);
            $table->decimal('report_value', 15, 2)->default(0);
            $table->date('sent_date')->nullable();
            $table->string('courier')->nullable();
            $table->string('tracking_no')->nullable();
            $table->date('received_date')->nullable();
            $table->string('recipient_name')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_stagings');
    }
};
