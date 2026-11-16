<?php

namespace Database\Seeders;

use App\Models\IncentiveDailyLog;
use App\Models\IncentiveRecord;
use App\Models\IncentiveSetting;
use App\Models\Outlet;
use App\Models\OutletAlias;
use App\Models\PayrollTask;
use App\Models\SalaryDailyLog;
use App\Models\SalaryRecord;
use App\Models\Spg;
use App\Models\TaskDateMultiplier;
use App\Models\User;
use App\Services\IncentiveCalculatorService;
use App\Services\SalaryCalculatorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $salaryCalc = app(SalaryCalculatorService::class);
        $incentiveCalc = app(IncentiveCalculatorService::class);

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
        $incentiveSettings = IncentiveSetting::firstOrCreate(
            ['id' => 1],
            [
                'threshold_amount' => 5000000,
                'tier_1_rate_above_threshold' => 1.25,
                'tier_2_rate_remaining' => 2.50,
                'below_threshold_flat_rate' => 2.50,
            ]
        );

        // 3. Purge all old tasks, daily logs, records
        DB::statement('PRAGMA foreign_keys = OFF;');
        SalaryDailyLog::truncate();
        SalaryRecord::truncate();
        IncentiveDailyLog::truncate();
        IncentiveRecord::truncate();
        TaskDateMultiplier::truncate();
        PayrollTask::truncate();
        DB::statement('PRAGMA foreign_keys = ON;');

        // 4. Seed Valid Master Outlets (33 Main Outlets & Aliases)
        $this->call(OutletSeeder::class);

        // 5. Seed Valid Master SPGs (20 Distinct SPGs)
        $this->call(SpgSeeder::class);

        $outlets = Outlet::all()->keyBy('outlet_code');
        $spgs = Spg::all()->keyBy('spg_name');

        // 6. Seed Clean Tasks with the 20 Valid SPGs and 33 Main Outlets
        $this->createSalaryTask(
            taskType: 'SALARY',
            month: 12,
            year: 2026,
            status: 'FINISHED',
            outlets: $outlets,
            spgs: $spgs,
            salaryCalc: $salaryCalc
        );

        $this->createIncentiveTask(
            taskType: 'INSENTIF',
            month: 12,
            year: 2026,
            status: 'FINISHED',
            outlets: $outlets,
            spgs: $spgs,
            incentiveCalc: $incentiveCalc,
            incentiveSettings: $incentiveSettings
        );

        $this->createSalaryTask(
            taskType: 'SALARY',
            month: 1,
            year: 2027,
            status: 'FINISHED',
            outlets: $outlets,
            spgs: $spgs,
            salaryCalc: $salaryCalc
        );

        $this->createIncentiveTask(
            taskType: 'INSENTIF',
            month: 1,
            year: 2027,
            status: 'FINISHED',
            outlets: $outlets,
            spgs: $spgs,
            incentiveCalc: $incentiveCalc,
            incentiveSettings: $incentiveSettings
        );

        $this->createSalaryTask(
            taskType: 'SALARY',
            month: 2,
            year: 2027,
            status: 'DRAFT',
            outlets: $outlets,
            spgs: $spgs,
            salaryCalc: $salaryCalc
        );
    }

    protected function createSalaryTask(string $taskType, int $month, int $year, string $status, $outlets, $spgs, SalaryCalculatorService $salaryCalc): PayrollTask
    {
        $periodInfo = PayrollTask::calculateWorkingPeriod($taskType, $month, $year);

        $task = PayrollTask::create([
            'task_type' => 'SALARY',
            'title' => 'Payroll Salary ' . $periodInfo['period_label'],
            'period_month' => $month,
            'period_year' => $year,
            'period_label' => $periodInfo['period_label'],
            'start_date' => $periodInfo['start_date'],
            'end_date' => $periodInfo['end_date'],
            'status' => $status,
            'notes' => 'Salary matrix period ' . $periodInfo['start_date'] . ' to ' . $periodInfo['end_date'] . ' (Dynamic Daily Outlets)',
            'finished_at' => $status === 'FINISHED' ? now() : null,
        ]);

        $dates = $task->getDatesList();

        foreach ($dates as $date) {
            $isSunday = $date->isSunday();
            TaskDateMultiplier::create([
                'payroll_task_id' => $task->id,
                'date' => $date->format('Y-m-d'),
                'multiplier' => $isSunday ? 2 : 1,
                'note' => $isSunday ? 'BESAR' : null,
            ]);
        }

        $outletsMap = $outlets->keyBy(fn($o) => strtoupper($o->outlet_code));
        $allAliases = OutletAlias::with('outlet')->get();
        $aliasMap = [];
        foreach ($allAliases as $alias) {
            $aliasMap[strtolower(trim($alias->alias))] = $alias->outlet;
        }

        foreach ($spgs as $spgName => $spg) {
            $defaultHome = $outlets['DELTA'] ?? $outlets->first();
            $record = SalaryRecord::create([
                'payroll_task_id' => $task->id,
                'spg_id' => $spg->id,
                'assigned_outlet_id' => $defaultHome->id,
                'reference_outlet_id' => $defaultHome->id,
                'uang_tambahan' => ($spgName === 'MEGA' ? 50000 : 0),
                'policy_100_percent_override' => false,
            ]);

            // PUJI tests the < 40% threshold rule (8 days / 26 = 30.77%)
            $targetPresentDays = ($spgName === 'PUJI') ? 8 : ($spgName === 'FITRI' ? 24 : 24);
            $presentCount = 0;

            foreach ($dates as $idx => $date) {
                $isSunday = $date->isSunday();
                $shouldBePresent = false;

                if ($spgName === 'PUJI') {
                    if ($presentCount < 8 && $idx % 3 === 0) {
                        $shouldBePresent = true;
                        $presentCount++;
                    }
                } else {
                    $dayOfWeek = $date->dayOfWeek;
                    $isOff = ($dayOfWeek === 1 && $idx % 2 === 0);
                    if (!$isOff && $presentCount < $targetPresentDays) {
                        $shouldBePresent = true;
                        $presentCount++;
                    }
                }

                $cellVal = '-';
                if ($shouldBePresent) {
                    if ($spgName === 'FITRI') {
                        // FITRI rotates: DELTA (most days), TP-2 (days 10-14), PA-1 (day 22)
                        if ($date->day >= 10 && $date->day <= 14) {
                            $cellVal = 'TP-2';
                        } elseif ($date->day == 22) {
                            $cellVal = 'PA-1';
                        } else {
                            $cellVal = 'DELTA';
                        }
                    } elseif ($spgName === 'MEGA') {
                        // MEGA rotates: R-1, and TP-1 on day 15
                        $cellVal = ($date->day == 15) ? 'TP-1' : 'R-1';
                    } elseif ($spgName === 'PITRI') {
                        // PITRI: distinct from FITRI, works mostly at ITC-UG
                        $cellVal = ($date->day == 25) ? 'DELTA' : 'ITC-UG';
                    } elseif ($spgName === 'DWI') {
                        // DWI: distinct from DWIE, works at PTC
                        $cellVal = 'PTC';
                    } elseif ($spgName === 'DWIE') {
                        // DWIE: distinct from DWI, works at LIPPO
                        $cellVal = 'LIPPO';
                    } else {
                        // Other SPGs distributed across valid outlets
                        $sampleCodes = ['R-2', 'PA-2', 'BG-L2', 'SNCT', 'CWS', 'KAZA', 'WTC', 'CT-1', 'GNT', 'DTC'];
                        $assignedCode = $sampleCodes[$spg->id % count($sampleCodes)] ?? 'DELTA';
                        $cellVal = $assignedCode;
                    }
                }

                $resolved = $salaryCalc->resolveCellPresenceAndOutlet($cellVal, $defaultHome, $outletsMap, $aliasMap);

                SalaryDailyLog::create([
                    'salary_record_id' => $record->id,
                    'date' => $date->format('Y-m-d'),
                    'raw_cell_value' => $cellVal,
                    'is_present' => $resolved['is_present'],
                    'matched_outlet_codes' => $resolved['matched_codes'],
                    'chosen_outlet_id' => $resolved['chosen_outlet_id'],
                    'outlet_code' => $resolved['outlet_code'],
                    'multiplier' => $isSunday ? 2 : 1,
                    'daily_uang_makan_utama' => 0,
                    'daily_uang_makan_tambahan' => 0,
                ]);
            }

            $salaryCalc->calculateRecord($record, true);
        }

        return $task;
    }

    protected function createIncentiveTask(string $taskType, int $month, int $year, string $status, $outlets, $spgs, IncentiveCalculatorService $incentiveCalc, IncentiveSetting $incentiveSettings): PayrollTask
    {
        $periodInfo = PayrollTask::calculateWorkingPeriod($taskType, $month, $year);

        $task = PayrollTask::create([
            'task_type' => 'INSENTIF',
            'title' => 'Payroll Insentif ' . $periodInfo['period_label'],
            'period_month' => $month,
            'period_year' => $year,
            'period_label' => $periodInfo['period_label'],
            'start_date' => $periodInfo['start_date'],
            'end_date' => $periodInfo['end_date'],
            'status' => $status,
            'notes' => 'Incentive matrix period ' . $periodInfo['start_date'] . ' to ' . $periodInfo['end_date'],
            'finished_at' => $status === 'FINISHED' ? now() : null,
        ]);

        $dates = $task->getDatesList();
        $daysCount = count($dates);

        $targetOmzetMap = [
            'MEGA' => ['amk' => 18000000, 'kopi' => 4500000],
            'FITRI' => ['amk' => 3500000, 'kopi' => 0],
            'PITRI' => ['amk' => 4000000, 'kopi' => 0],
            'DWI' => ['amk' => 7000000, 'kopi' => 0],
            'DWIE' => ['amk' => 9000000, 'kopi' => 1000000],
            'NADILA' => ['amk' => 16200000, 'kopi' => 2000000],
            'YANI' => ['amk' => 14500000, 'kopi' => 0],
            'TINA' => ['amk' => 8800000, 'kopi' => 1000000],
            'ZILDA' => ['amk' => 6200000, 'kopi' => 0],
            'WAHYU' => ['amk' => 22100000, 'kopi' => 3000000],
            'DINNI' => ['amk' => 4200000, 'kopi' => 0],
            'LINA' => ['amk' => 3800000, 'kopi' => 0],
            'PUJI' => ['amk' => 2900000, 'kopi' => 0],
        ];

        foreach ($spgs as $spgName => $spg) {
            $defaultHome = $outlets['DELTA'] ?? $outlets->first();
            $record = IncentiveRecord::create([
                'payroll_task_id' => $task->id,
                'spg_id' => $spg->id,
                'assigned_outlet_id' => $defaultHome->id,
                'reference_outlet_id' => $defaultHome->id,
                'uang_tambahan' => 0,
            ]);

            $targets = $targetOmzetMap[$spgName] ?? ['amk' => 5000000, 'kopi' => 0];
            $dailyAmkBase = round($targets['amk'] / $daysCount);
            $dailyKopiBase = round($targets['kopi'] / $daysCount);

            $runningAmk = 0;
            $runningKopi = 0;

            foreach ($dates as $idx => $date) {
                if ($idx === $daysCount - 1) {
                    $dayAmk = $targets['amk'] - $runningAmk;
                    $dayKopi = $targets['kopi'] - $runningKopi;
                } else {
                    $dayAmk = $dailyAmkBase;
                    $dayKopi = $dailyKopiBase;
                    $runningAmk += $dayAmk;
                    $runningKopi += $dayKopi;
                }

                $workOutlet = $outlets['DELTA'] ?? null;
                if ($spgName === 'FITRI' && $date->day >= 10 && $date->day <= 15) {
                    $workOutlet = $outlets['TP-2'] ?? $workOutlet;
                } elseif ($spgName === 'MEGA') {
                    $workOutlet = $outlets['R-1'] ?? $workOutlet;
                } elseif ($spgName === 'PITRI') {
                    $workOutlet = $outlets['ITC-UG'] ?? $workOutlet;
                } elseif ($spgName === 'DWI') {
                    $workOutlet = $outlets['PTC'] ?? $workOutlet;
                } elseif ($spgName === 'DWIE') {
                    $workOutlet = $outlets['LIPPO'] ?? $workOutlet;
                }

                IncentiveDailyLog::create([
                    'incentive_record_id' => $record->id,
                    'date' => $date->format('Y-m-d'),
                    'outlet_id' => $workOutlet ? $workOutlet->id : null,
                    'outlet_code' => $workOutlet ? $workOutlet->outlet_code : null,
                    'omzet_amk' => $dayAmk,
                    'omzet_amkopi' => $dayKopi,
                    'total_daily_omzet' => $dayAmk + $dayKopi,
                ]);
            }

            $incentiveCalc->calculateRecord($record, $incentiveSettings, true);
        }

        return $task;
    }
}
