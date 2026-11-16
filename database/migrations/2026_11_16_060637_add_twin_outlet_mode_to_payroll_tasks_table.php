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
        Schema::table('payroll_tasks', function (Blueprint $table) {
            $table->string('twin_outlet_mode', 20)->default('split')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_tasks', function (Blueprint $table) {
            $table->dropColumn('twin_outlet_mode');
        });
    }
};
