<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('incentive_daily_logs', function (Blueprint $table) {
            $table->decimal('raw_outlet_amk', 15, 2)->default(0)->after('omzet_amk');
            $table->decimal('raw_outlet_kopi', 15, 2)->default(0)->after('omzet_amkopi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incentive_daily_logs', function (Blueprint $table) {
            $table->dropColumn(['raw_outlet_amk', 'raw_outlet_kopi']);
        });
    }
};
