<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('report_no')->unique();
            $table->date('report_date');
            $table->string('purpose');
            $table->decimal('resume_value', 15, 2)->nullable();
            $table->decimal('report_value', 15, 2)->nullable();
            $table->date('print_date')->nullable();
            $table->timestamp('final_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
