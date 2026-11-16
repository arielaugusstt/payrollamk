<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_task_id')->constrained('payroll_tasks')->cascadeOnDelete();
            $table->foreignId('spg_id')->constrained('spgs')->cascadeOnDelete();
            $table->foreignId('assigned_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->decimal('total_omzet_amk', 15, 2)->default(0);
            $table->decimal('total_omzet_amkopi', 15, 2)->default(0);
            $table->decimal('total_omzet', 15, 2)->default(0);
            $table->decimal('insentif_tier_1', 15, 2)->default(0);
            $table->decimal('insentif_tier_2', 15, 2)->default(0);
            $table->decimal('insentif_murni', 15, 2)->default(0);
            $table->decimal('uang_tambahan', 15, 2)->default(0);
            $table->decimal('total_insentif', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_task_id', 'spg_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_records');
    }
};
