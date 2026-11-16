<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_record_id')->constrained('salary_records')->cascadeOnDelete();
            $table->date('date')->index();
            $table->string('raw_cell_value')->nullable();
            $table->boolean('is_present')->default(false);
            $table->json('matched_outlet_codes')->nullable();
            $table->foreignId('chosen_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->unsignedTinyInteger('multiplier')->default(1);
            $table->decimal('daily_uang_makan_utama', 15, 2)->default(0);
            $table->decimal('daily_uang_makan_tambahan', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['salary_record_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_daily_logs');
    }
};
