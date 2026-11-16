<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spgs', function (Blueprint $table) {
            $table->foreignId('default_outlet_id')->nullable()->after('spg_name')->constrained('outlets')->nullOnDelete();
        });

        Schema::table('salary_records', function (Blueprint $table) {
            $table->foreignId('reference_outlet_id')->nullable()->after('assigned_outlet_id')->constrained('outlets')->nullOnDelete();
            $table->json('outlets_summary')->nullable()->after('total_hari_masuk'); // e.g. {"DELTA": 18, "TP-2": 6}
        });

        Schema::table('salary_daily_logs', function (Blueprint $table) {
            $table->string('outlet_code')->nullable()->after('chosen_outlet_id');
        });

        Schema::table('incentive_records', function (Blueprint $table) {
            $table->foreignId('reference_outlet_id')->nullable()->after('assigned_outlet_id')->constrained('outlets')->nullOnDelete();
            $table->json('outlets_summary')->nullable()->after('total_omzet');
        });

        Schema::table('incentive_daily_logs', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->after('date')->constrained('outlets')->nullOnDelete();
            $table->string('outlet_code')->nullable()->after('outlet_id');
        });
    }

    public function down(): void
    {
        Schema::table('incentive_daily_logs', function (Blueprint $table) {
            $table->dropColumn(['outlet_id', 'outlet_code']);
        });

        Schema::table('incentive_records', function (Blueprint $table) {
            $table->dropColumn(['reference_outlet_id', 'outlets_summary']);
        });

        Schema::table('salary_daily_logs', function (Blueprint $table) {
            $table->dropColumn(['outlet_code']);
        });

        Schema::table('salary_records', function (Blueprint $table) {
            $table->dropColumn(['reference_outlet_id', 'outlets_summary']);
        });

        Schema::table('spgs', function (Blueprint $table) {
            $table->dropColumn(['default_outlet_id']);
        });
    }
};
