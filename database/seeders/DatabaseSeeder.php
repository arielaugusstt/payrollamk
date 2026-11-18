<?php

namespace Database\Seeders;

use App\Models\BankOmzetDaily;
use App\Models\BankPresensiDaily;
use App\Models\IncentiveDailyLog;
use App\Models\IncentiveRecord;
use App\Models\IncentiveSetting;
use App\Models\MonthlyDataPeriod;
use App\Models\PayrollTask;
use App\Models\SalaryDailyLog;
use App\Models\SalaryRecord;
use App\Models\TaskDateMultiplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Clean all transactional/dummy data and seed only:
     * 1. Admin User
     * 2. Incentive Formula Settings
     * 3. 33 Main Regular Outlets & Official Aliases
     * 4. 23 Distinct Master SPGs
     */
    public function run(): void
    {
        // 1. Single Admin User (Preserved / Ensured)
        User::firstOrCreate(
            ['email' => 'admin@payroll.local'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('password123'),
            ]
        );

        // 2. Global Incentive Settings
        IncentiveSetting::firstOrCreate(
            ['id' => 1],
            [
                'threshold_amount' => 5000000,
                'tier_1_rate_above_threshold' => 1.25,
                'tier_2_rate_remaining' => 2.50,
                'below_threshold_flat_rate' => 2.50,
            ]
        );

        // 3. Purge all transactional tasks, daily logs, records, and bank data
        Schema::disableForeignKeyConstraints();
        if (Schema::hasTable('bank_presensi_daily')) {
            BankPresensiDaily::truncate();
        }
        if (Schema::hasTable('bank_omzet_daily')) {
            BankOmzetDaily::truncate();
        }
        if (Schema::hasTable('monthly_data_periods')) {
            MonthlyDataPeriod::truncate();
        }
        SalaryDailyLog::truncate();
        SalaryRecord::truncate();
        IncentiveDailyLog::truncate();
        IncentiveRecord::truncate();
        TaskDateMultiplier::truncate();
        PayrollTask::truncate();
        Schema::enableForeignKeyConstraints();

        // 4. Seed Valid Master Outlets (33 Main Regular Outlets & Official Aliases)
        $this->call(OutletSeeder::class);

        // 5. Seed Valid Master SPGs (Exact 23 Distinct SPGs)
        $this->call(SpgSeeder::class);
    }
}
