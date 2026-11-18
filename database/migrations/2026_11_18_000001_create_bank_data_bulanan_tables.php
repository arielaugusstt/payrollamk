<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_data_periods', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('month');
            $table->boolean('has_presensi')->default(false);
            $table->boolean('has_omzet')->default(false);
            $table->timestamp('presensi_uploaded_at')->nullable();
            $table->timestamp('omzet_uploaded_at')->nullable();
            $table->string('presensi_filename')->nullable();
            $table->string('omzet_filename')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('bank_presensi_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('monthly_data_periods')->cascadeOnDelete();
            $table->date('work_date');
            $table->integer('day_number');
            $table->foreignId('spg_id')->constrained('spgs')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('raw_outlet_text')->nullable();
            $table->timestamps();

            $table->unique(['work_date', 'spg_id']);
            $table->index(['period_id', 'day_number']);
        });

        Schema::create('bank_omzet_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('monthly_data_periods')->cascadeOnDelete();
            $table->date('omzet_date');
            $table->integer('day_number');
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->decimal('omzet_amk', 15, 2)->default(0);
            $table->decimal('omzet_amkopi', 15, 2)->default(0);
            $table->decimal('total_omzet', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['omzet_date', 'outlet_id']);
            $table->index(['period_id', 'day_number']);
        });

        Schema::table('salary_daily_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_daily_logs', 'custom_nominal_override')) {
                $table->decimal('custom_nominal_override', 15, 2)->nullable()->after('daily_uang_makan_tambahan');
            }
            if (!Schema::hasColumn('salary_daily_logs', 'is_manual_override')) {
                $table->boolean('is_manual_override')->default(false)->after('custom_nominal_override');
            }
        });

        Schema::table('incentive_daily_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('incentive_daily_logs', 'is_manual_override')) {
                $table->boolean('is_manual_override')->default(false)->after('total_daily_omzet');
            }
        });
    }

    public function down(): void
    {
        Schema::table('incentive_daily_logs', function (Blueprint $table) {
            $table->dropColumn(['is_manual_override']);
        });

        Schema::table('salary_daily_logs', function (Blueprint $table) {
            $table->dropColumn(['custom_nominal_override', 'is_manual_override']);
        });

        Schema::dropIfExists('bank_omzet_daily');
        Schema::dropIfExists('bank_presensi_daily');
        Schema::dropIfExists('monthly_data_periods');
    }
};
