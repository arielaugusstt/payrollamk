<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_task_id')->constrained('payroll_tasks')->cascadeOnDelete();
            $table->foreignId('spg_id')->constrained('spgs')->cascadeOnDelete();
            $table->foreignId('assigned_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->unsignedInteger('total_hari_masuk')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->default(0);
            $table->decimal('uang_makan_utama', 15, 2)->default(0);
            $table->decimal('uang_makan_tambahan', 15, 2)->default(0);
            $table->decimal('base_salary_outlet', 15, 2)->default(0);
            $table->decimal('base_salary_recommended', 15, 2)->default(0);
            $table->boolean('policy_100_percent_override')->default(false);
            $table->decimal('base_salary_final', 15, 2)->default(0);
            $table->decimal('uang_pulsa', 15, 2)->default(0);
            $table->decimal('uang_tambahan', 15, 2)->default(0);
            $table->decimal('total_salary', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_task_id', 'spg_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_records');
    }
};
