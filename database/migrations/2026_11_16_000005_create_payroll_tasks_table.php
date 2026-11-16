<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_type'); // 'SALARY' or 'INSENTIF'
            $table->string('title');
            $table->unsignedSmallInteger('period_month'); // 1-12
            $table->unsignedSmallInteger('period_year'); // e.g. 2026
            $table->string('period_label'); // e.g. "Desember 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('DRAFT'); // 'DRAFT', 'FINISHED'
            $table->text('notes')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_tasks');
    }
};
