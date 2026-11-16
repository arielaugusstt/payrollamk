<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_date_multipliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_task_id')->constrained('payroll_tasks')->cascadeOnDelete();
            $table->date('date')->index();
            $table->unsignedTinyInteger('multiplier')->default(1); // 1, 2, 3
            $table->string('note')->nullable(); // e.g. "BESAR", "Idul Fitri", etc.
            $table->timestamps();

            $table->unique(['payroll_task_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_date_multipliers');
    }
};
