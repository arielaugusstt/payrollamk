<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('threshold_amount', 15, 2)->default(5000000);
            $table->decimal('tier_1_rate_above_threshold', 5, 2)->default(1.25);
            $table->decimal('tier_2_rate_remaining', 5, 2)->default(2.50);
            $table->decimal('below_threshold_flat_rate', 5, 2)->default(2.50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_settings');
    }
};
