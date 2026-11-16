<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_record_id')->constrained('incentive_records')->cascadeOnDelete();
            $table->date('date')->index();
            $table->decimal('omzet_amk', 15, 2)->default(0);
            $table->decimal('omzet_amkopi', 15, 2)->default(0);
            $table->decimal('total_daily_omzet', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['incentive_record_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_daily_logs');
    }
};
