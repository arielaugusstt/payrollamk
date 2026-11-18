<?php

namespace App\Services;

use App\Models\BankOmzetDaily;
use App\Models\BankPresensiDaily;
use App\Models\IncentiveDailyLog;
use App\Models\IncentiveRecord;
use App\Models\IncentiveSetting;
use App\Models\MonthlyDataPeriod;
use App\Models\Outlet;
use App\Models\OutletAlias;
use App\Models\PayrollTask;
use App\Models\SalaryDailyLog;
use App\Models\SalaryRecord;
use App\Models\Spg;
use App\Models\TaskDateMultiplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BankDataService
{
    protected SalaryCalculatorService $salaryCalc;
    protected IncentiveCalculatorService $incentiveCalc;
    protected static array $fileRowCache = [];

    protected const IGNORED_OMZET_LABELS = [
        'GREEN TEA',
        'THAI TEA',
        'CHOCO',
        'PTT',
        'DONUTS',
        '0',
        'NAN',
        'TOTAL',
        'JUMLAH',
        'SUBTOTAL',
    ];

    public function __construct(
        SalaryCalculatorService $salaryCalc,
        IncentiveCalculatorService $incentiveCalc
    ) {
        $this->salaryCalc = $salaryCalc;
        $this->incentiveCalc = $incentiveCalc;
    }

    /**
     * Build lookup maps for Outlets, Aliases, and SPGs.
     */
    public function getLookupMaps(): array
    {
        $allOutlets = Outlet::all();
        $outletsByCode = [];
        foreach ($allOutlets as $outlet) {
            $outletsByCode[strtoupper(trim($outlet->outlet_code))] = $outlet;
        }

        $aliases = OutletAlias::with('outlet')->get();
        $aliasMap = [];
        foreach ($aliases as $alias) {
            if ($alias->outlet) {
                $aliasMap[strtolower(trim($alias->alias))] = $alias->outlet;
            }
        }

        $hardcodedSynonyms = [
            'suncity' => 'SNCT',
            'grandcity' => 'GC',
            'grand city' => 'GC',
            'gc' => 'GC',
            'delta' => 'DELTA',
            'elta' => 'DELTA',
            'lip' => 'LIPPO',
            'lippo' => 'LIPPO',
            'wt' => 'WTC',
            'wtc' => 'WTC',
            'ka' => 'KAZA',
            'kaza' => 'KAZA',
            'giant' => 'GNT',
            'gnt' => 'GNT',
            'ciputra' => 'CWS',
            'cws' => 'CWS',
            'ptc' => 'PTC',
            'cito' => 'CT-1',
            'marina' => 'RMY',
            'bg-ll' => 'BG-LL',
            'bg-l2' => 'BG-L2',
            'bg-lg' => 'BG-LG',
            'bg-gl' => 'BG-GL',
        ];
        foreach ($hardcodedSynonyms as $syn => $targetCode) {
            if (!isset($aliasMap[$syn]) && isset($outletsByCode[$targetCode])) {
                $aliasMap[$syn] = $outletsByCode[$targetCode];
            }
        }

        $allSpgs = Spg::all();
        $spgByName = [];
        foreach ($allSpgs as $spg) {
            $spgByName[strtolower(trim($spg->spg_name))] = $spg;
            if (is_array($spg->aliases)) {
                foreach ($spg->aliases as $al) {
                    $spgByName[strtolower(trim($al))] = $spg;
                }
            }
        }

        return [
            'allOutlets' => $allOutlets,
            'outletsByCode' => $outletsByCode,
            'aliasMap' => $aliasMap,
            'allSpgs' => $allSpgs,
            'spgByName' => $spgByName,
        ];
    }

    /**
     * Resolve an outlet string (case-insensitive + official aliases).
     */
    public function resolveOutlet(?string $raw, array $outletsByCode, array $aliasMap): ?Outlet
    {
        if ($raw === null) {
            return null;
        }
        $clean = trim($raw);
        if ($clean === '' || $clean === '-' || strtoupper($clean) === 'OFF' || strtoupper($clean) === 'LIBUR' || strtoupper($clean) === 'X') {
            return null;
        }

        $upper = strtoupper($clean);
        if (isset($outletsByCode[$upper])) {
            return $outletsByCode[$upper];
        }

        $lower = strtolower($clean);
        if (isset($aliasMap[$lower])) {
            return $aliasMap[$lower];
        }

        // Tokenized fallback
        $tokens = preg_split('/[\s,\/]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $bestOutlet = null;
        $highestRate = -1;
        foreach ($tokens as $tok) {
            $uTok = strtoupper(trim($tok));
            $lTok = strtolower(trim($tok));
            $cand = $outletsByCode[$uTok] ?? ($aliasMap[$lTok] ?? null);
            if ($cand && (float)$cand->uang_makan_utama > $highestRate) {
                $highestRate = (float)$cand->uang_makan_utama;
                $bestOutlet = $cand;
            }
        }

        return $bestOutlet;
    }

    /**
     * Slot 1: Import Full-Month Presensi Excel (Dates 1..31 -> YYYY-MM-DD in bank_presensi_daily).
     * Format: Sheet1 (Row 1 Col B..AO = SPG Names, Col A Row 2..32 = Day 1..31, Cell = Outlet Code).
     */
    public function importPresensi(string $filePath, int $year, int $month, ?string $originalFilename = null): array
    {
        $cacheKey = 'presensi_' . @md5_file($filePath);
        if (isset(self::$fileRowCache[$cacheKey])) {
            $rawRows = self::$fileRowCache[$cacheKey];
        } else {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = null;
            foreach ($spreadsheet->getSheetNames() as $name) {
                if (stripos($name, 'presensi') !== false || stripos($name, 'sheet1') !== false) {
                    $sheet = $spreadsheet->getSheetByName($name);
                    break;
                }
            }
            if (!$sheet) {
                $sheet = $spreadsheet->getSheet(0);
            }

            $rawRows = $sheet->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
            unset($sheet, $spreadsheet);
            self::$fileRowCache[$cacheKey] = $rawRows;
        }

        $maps = $this->getLookupMaps();
        $outletsByCode = $maps['outletsByCode'];
        $aliasMap = $maps['aliasMap'];
        $spgByName = $maps['spgByName'];

        $headerRowIdx = 0;
        $headerRow = $rawRows[$headerRowIdx] ?? [];

        // Map day_number (1..31) -> row index
        $dayToRowIdx = [];
        for ($r = 1; $r < count($rawRows); $r++) {
            $colA = trim((string)($rawRows[$r][0] ?? ''));
            if (is_numeric($colA)) {
                $dayNum = (int)$colA;
                if ($dayNum >= 1 && $dayNum <= 31) {
                    $dayToRowIdx[$dayNum] = $r;
                }
            }
        }

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Parse SPG columns from Row 1 (Col B onwards)
        // Merge duplicate column names if one is empty and the other has data
        $spgAttendanceByUpperName = []; // upperName => ['raw_name' => string, 'days' => [day => ['raw' => string, 'outlet' => ?Outlet]], 'working_days' => int]
        $maxCols = count($headerRow);

        for ($col = 1; $col < $maxCols; $col++) {
            $rawHeader = trim((string)($headerRow[$col] ?? ''));
            if ($rawHeader === '') {
                continue;
            }
            // Strip pandas duplicate suffix like "SHELA.1" -> "SHELA"
            $cleanName = preg_replace('/\.\d+$/', '', $rawHeader);
            $upperName = strtoupper(trim($cleanName));
            if (in_array($upperName, ['NO', 'TANGGAL', 'TGL', 'DATE', 'HARI', 'TOTAL', 'JUMLAH'])) {
                continue;
            }

            if (!isset($spgAttendanceByUpperName[$upperName])) {
                $spgAttendanceByUpperName[$upperName] = [
                    'raw_name' => $upperName,
                    'days' => [],
                    'working_days' => 0,
                ];
            }

            for ($day = 1; $day <= 31; $day++) {
                $rIdx = $dayToRowIdx[$day] ?? null;
                $rawVal = $rIdx !== null ? trim((string)($rawRows[$rIdx][$col] ?? '')) : '';
                $outlet = $this->resolveOutlet($rawVal, $outletsByCode, $aliasMap);

                // Only overwrite day if existing day for this duplicate name is empty
                $existingDay = $spgAttendanceByUpperName[$upperName]['days'][$day] ?? null;
                if (!$existingDay || ($existingDay['outlet'] === null && $outlet !== null)) {
                    $spgAttendanceByUpperName[$upperName]['days'][$day] = [
                        'raw' => $rawVal,
                        'outlet' => $outlet,
                    ];
                }
            }
        }

        // Recount working_days per SPG column
        foreach ($spgAttendanceByUpperName as $uName => &$info) {
            $wDays = 0;
            foreach ($info['days'] as $dayNum => $dData) {
                if ($dayNum <= $daysInMonth && $dData['outlet'] !== null) {
                    $wDays++;
                }
            }
            $info['working_days'] = $wDays;
        }
        unset($info);

        return DB::transaction(function () use (
            $year,
            $month,
            $daysInMonth,
            $originalFilename,
            $spgAttendanceByUpperName,
            &$spgByName
        ) {
            $period = MonthlyDataPeriod::firstOrCreate(
                ['year' => $year, 'month' => $month],
                ['has_presensi' => false, 'has_omzet' => false]
            );

            // Ensure any active SPG (> 0 working days) in the Excel is registered in Master SPGs
            // Empty columns with 0 attendance (e.g. AINI, may, desi, etc.) are accepted gracefully without polluting Master SPGs
            foreach ($spgAttendanceByUpperName as $uName => $info) {
                $lName = strtolower($uName);
                if (!isset($spgByName[$lName]) && $info['working_days'] > 0) {
                    $newSpg = Spg::create([
                        'spg_name' => $uName,
                        'default_outlet_id' => null,
                        'assigned_outlet_id' => null,
                        'is_active' => true,
                        'aliases' => [$lName],
                    ]);
                    $spgByName[$lName] = $newSpg;
                }
            }

            // Now save daily records for all Master SPGs across 1..daysInMonth
            $allMasterSpgs = Spg::where('is_active', true)->get();
            $activeSpgCount = 0;
            $zeroSpgNames = [];

            // Clear old presensi for this period before inserting fresh in bulk
            BankPresensiDaily::where('period_id', $period->id)->delete();

            $now = now();
            $insertBatch = [];

            foreach ($allMasterSpgs as $spg) {
                $uName = strtoupper(trim($spg->spg_name));
                $excelInfo = $spgAttendanceByUpperName[$uName] ?? null;
                $spgWorkingDays = 0;

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $workDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $dayCell = $excelInfo['days'][$day] ?? ['raw' => '', 'outlet' => null];
                    $outlet = $dayCell['outlet'];
                    $rawText = $outlet ? $outlet->outlet_code : ($dayCell['raw'] !== '' ? $dayCell['raw'] : null);

                    if ($outlet) {
                        $spgWorkingDays++;
                    }

                    $insertBatch[] = [
                        'period_id' => $period->id,
                        'work_date' => $workDate,
                        'day_number' => $day,
                        'spg_id' => $spg->id,
                        'outlet_id' => $outlet ? $outlet->id : null,
                        'raw_outlet_text' => $rawText,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($spgWorkingDays > 0) {
                    $activeSpgCount++;
                } else {
                    $zeroSpgNames[] = $spg->spg_name;
                }
            }

            foreach (array_chunk($insertBatch, 250) as $chunk) {
                BankPresensiDaily::upsert(
                    $chunk,
                    ['work_date', 'spg_id'],
                    ['period_id', 'day_number', 'outlet_id', 'raw_outlet_text', 'updated_at']
                );
            }

            // Also track any 0-attendance columns from Excel for informational summary
            foreach ($spgAttendanceByUpperName as $uName => $info) {
                if ($info['working_days'] === 0 && !in_array($uName, $zeroSpgNames)) {
                    $zeroSpgNames[] = $uName;
                }
            }

            $period->update([
                'has_presensi' => true,
                'presensi_uploaded_at' => now(),
                'presensi_filename' => $originalFilename ?? basename($filePath),
            ]);

            // Auto-sync any DRAFT tasks that depend on this period
            $this->autoSyncDependentDraftTasks($year, $month);

            return [
                'period' => $period,
                'active_spg_count' => $activeSpgCount,
                'zero_spg_count' => count($zeroSpgNames),
                'days_imported' => $daysInMonth,
            ];
        });
    }

    /**
     * Slot 2: Import Full-Month Omzet Excel (Dates 1..31 -> YYYY-MM-DD in bank_omzet_daily).
     * Format: Sheet 'omset' or First Sheet (Row 1 Col B..AF = Day 1..31, Col A Row 2..117 = Outlet & Sub-Rows).
     */
    public function importOmzet(string $filePath, int $year, int $month, ?string $originalFilename = null): array
    {
        $cacheKey = 'omzet_' . @md5_file($filePath);
        if (isset(self::$fileRowCache[$cacheKey])) {
            $rawRows = self::$fileRowCache[$cacheKey];
        } else {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = null;
            foreach ($spreadsheet->getSheetNames() as $name) {
                if (stripos($name, 'omset') !== false || stripos($name, 'omzet') !== false) {
                    $sheet = $spreadsheet->getSheetByName($name);
                    break;
                }
            }
            if (!$sheet) {
                if ($spreadsheet->getSheetCount() >= 2) {
                    $sheet = $spreadsheet->getSheet(1);
                } else {
                    $sheet = $spreadsheet->getSheet(0);
                }
            }

            $rawRows = $sheet->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
            unset($sheet, $spreadsheet);
            self::$fileRowCache[$cacheKey] = $rawRows;
        }

        $maps = $this->getLookupMaps();
        $outletsByCode = $maps['outletsByCode'];
        $aliasMap = $maps['aliasMap'];
        $allOutlets = $maps['allOutlets'];

        // Find header row with dates 1..31
        $headerRowIdx = 0;
        for ($r = 0; $r < min(10, count($rawRows)); $r++) {
            $matches = 0;
            foreach ($rawRows[$r] as $cIdx => $val) {
                if ($cIdx === 0) continue;
                $t = trim((string)$val);
                if (is_numeric($t) && (int)$t >= 1 && (int)$t <= 31) {
                    $matches++;
                }
            }
            if ($matches >= 10) {
                $headerRowIdx = $r;
                break;
            }
        }

        $headerRow = $rawRows[$headerRowIdx] ?? [];
        $colToDay = [];
        foreach ($headerRow as $cIdx => $val) {
            if ($cIdx === 0) continue;
            $t = trim((string)$val);
            if (is_numeric($t)) {
                $d = (int)$t;
                if ($d >= 1 && $d <= 31) {
                    $colToDay[$cIdx] = $d;
                }
            }
        }

        // Accumulate AMK and AMKopi per (outlet_id, day) using +=
        $accumulated = []; // outlet_id => [day => ['amk' => float, 'kopi' => float]]
        foreach ($allOutlets as $outlet) {
            $accumulated[$outlet->id] = [];
            for ($d = 1; $d <= 31; $d++) {
                $accumulated[$outlet->id][$d] = ['amk' => 0.0, 'kopi' => 0.0];
            }
        }

        $totalRows = count($rawRows);
        for ($r = $headerRowIdx + 1; $r < $totalRows; $r++) {
            $row = $rawRows[$r];
            $rawLabel = trim((string)($row[0] ?? ''));
            if ($rawLabel === '') {
                continue;
            }

            $upperLabel = strtoupper($rawLabel);
            if (in_array($upperLabel, self::IGNORED_OMZET_LABELS, true)) {
                continue;
            }

            // Detect AMKopi <OUTLET>
            $isKopi = false;
            $targetOutletText = $rawLabel;
            if (preg_match('/^am\s*kopi\s+(.+)$/i', $rawLabel, $m)) {
                $isKopi = true;
                $targetOutletText = trim($m[1]);
            }

            $outlet = $this->resolveOutlet($targetOutletText, $outletsByCode, $aliasMap);
            if (!$outlet) {
                continue;
            }

            foreach ($colToDay as $cIdx => $day) {
                $amount = $this->parseNumeric($row[$cIdx] ?? null);
                if ($amount > 0) {
                    if ($isKopi) {
                        $accumulated[$outlet->id][$day]['kopi'] += $amount;
                    } else {
                        $accumulated[$outlet->id][$day]['amk'] += $amount;
                    }
                }
            }
        }

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        return DB::transaction(function () use (
            $year,
            $month,
            $daysInMonth,
            $originalFilename,
            $filePath,
            $allOutlets,
            $accumulated
        ) {
            $period = MonthlyDataPeriod::firstOrCreate(
                ['year' => $year, 'month' => $month],
                ['has_presensi' => false, 'has_omzet' => false]
            );

            BankOmzetDaily::where('period_id', $period->id)->delete();

            $activeOutletsCount = 0;
            $grandTotalOmzet = 0.0;
            $now = now();
            $insertBatch = [];

            foreach ($allOutlets as $outlet) {
                $outletSum = 0.0;
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $omzetDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $amk = (float)($accumulated[$outlet->id][$day]['amk'] ?? 0.0);
                    $kopi = (float)($accumulated[$outlet->id][$day]['kopi'] ?? 0.0);
                    $total = $amk + $kopi;
                    $outletSum += $total;
                    $grandTotalOmzet += $total;

                    $insertBatch[] = [
                        'period_id' => $period->id,
                        'omzet_date' => $omzetDate,
                        'day_number' => $day,
                        'outlet_id' => $outlet->id,
                        'omzet_amk' => $amk,
                        'omzet_amkopi' => $kopi,
                        'total_omzet' => $total,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($outletSum > 0) {
                    $activeOutletsCount++;
                }
            }

            foreach (array_chunk($insertBatch, 250) as $chunk) {
                BankOmzetDaily::upsert(
                    $chunk,
                    ['omzet_date', 'outlet_id'],
                    ['period_id', 'day_number', 'omzet_amk', 'omzet_amkopi', 'total_omzet', 'updated_at']
                );
            }

            $period->update([
                'has_omzet' => true,
                'omzet_uploaded_at' => now(),
                'omzet_filename' => $originalFilename ?? basename($filePath),
            ]);

            // Auto-sync any DRAFT tasks that depend on this period
            $this->autoSyncDependentDraftTasks($year, $month);

            return [
                'period' => $period,
                'active_outlets_count' => $activeOutletsCount,
                'grand_total_omzet' => $grandTotalOmzet,
                'days_imported' => $daysInMonth,
            ];
        });
    }

    /**
     * Check if Bank Data Bulanan has the required monthly uploads for a given task.
     */
    public function checkTaskReadiness(string $taskType, int $payrollMonth, int $payrollYear): array
    {
        $targetDate = Carbon::createFromDate($payrollYear, $payrollMonth, 1);
        $missing = [];
        $requiredPeriods = [];

        if ($taskType === 'SALARY') {
            // Needs Presensi of Month-2 (21..end) and Presensi of Month-1 (1..20)
            $m2 = $targetDate->copy()->subMonths(2);
            $m1 = $targetDate->copy()->subMonth();

            $p2 = MonthlyDataPeriod::where('year', $m2->year)->where('month', $m2->month)->first();
            $p1 = MonthlyDataPeriod::where('year', $m1->year)->where('month', $m1->month)->first();

            $m2Label = MonthlyDataPeriod::monthName($m2->month) . ' ' . $m2->year;
            $m1Label = MonthlyDataPeriod::monthName($m1->month) . ' ' . $m1->year;

            $requiredPeriods[] = [
                'type' => 'PRESENSI',
                'year' => $m2->year,
                'month' => $m2->month,
                'label' => "Presensi {$m2Label} (Tgl 21–{$m2->daysInMonth})",
                'is_ready' => (bool)($p2 && $p2->has_presensi),
            ];
            if (!$p2 || !$p2->has_presensi) {
                $missing[] = [
                    'type' => 'PRESENSI',
                    'year' => $m2->year,
                    'month' => $m2->month,
                    'label' => "Presensi {$m2Label}",
                ];
            }

            $requiredPeriods[] = [
                'type' => 'PRESENSI',
                'year' => $m1->year,
                'month' => $m1->month,
                'label' => "Presensi {$m1Label} (Tgl 1–20)",
                'is_ready' => (bool)($p1 && $p1->has_presensi),
            ];
            if (!$p1 || !$p1->has_presensi) {
                $missing[] = [
                    'type' => 'PRESENSI',
                    'year' => $m1->year,
                    'month' => $m1->month,
                    'label' => "Presensi {$m1Label}",
                ];
            }
        } else {
            // INSENTIF: Needs Presensi of Month-1 AND Omzet of Month-1 (1..end)
            $m1 = $targetDate->copy()->subMonth();
            $p1 = MonthlyDataPeriod::where('year', $m1->year)->where('month', $m1->month)->first();
            $m1Label = MonthlyDataPeriod::monthName($m1->month) . ' ' . $m1->year;

            $requiredPeriods[] = [
                'type' => 'PRESENSI',
                'year' => $m1->year,
                'month' => $m1->month,
                'label' => "Presensi {$m1Label} (Tgl 1–{$m1->daysInMonth})",
                'is_ready' => (bool)($p1 && $p1->has_presensi),
            ];
            if (!$p1 || !$p1->has_presensi) {
                $missing[] = [
                    'type' => 'PRESENSI',
                    'year' => $m1->year,
                    'month' => $m1->month,
                    'label' => "Presensi {$m1Label}",
                ];
            }

            $requiredPeriods[] = [
                'type' => 'OMZET',
                'year' => $m1->year,
                'month' => $m1->month,
                'label' => "Omzet {$m1Label} (Tgl 1–{$m1->daysInMonth})",
                'is_ready' => (bool)($p1 && $p1->has_omzet),
            ];
            if (!$p1 || !$p1->has_omzet) {
                $missing[] = [
                    'type' => 'OMZET',
                    'year' => $m1->year,
                    'month' => $m1->month,
                    'label' => "Omzet {$m1Label}",
                ];
            }
        }

        return [
            'is_ready' => empty($missing),
            'required_periods' => $requiredPeriods,
            'missing' => $missing,
        ];
    }

    /**
     * Populate / Re-Sync a PayrollTask (SALARY or INSENTIF) from Bank Data Bulanan.
     */
    public function syncTaskFromBankData(PayrollTask $task, bool $preserveManualOverrides = true): array
    {
        return DB::transaction(function () use ($task, $preserveManualOverrides) {
            $dates = $task->getDatesList();
            $startStr = $task->start_date->format('Y-m-d');
            $endStr = $task->end_date->format('Y-m-d');

            $allSpgs = Spg::where('is_active', true)->orderBy('id')->get();
            $outletsById = Outlet::all()->keyBy('id');

            // Fetch BankPresensiDaily for the task date range
            $presensiLogs = BankPresensiDaily::whereDate('work_date', '>=', $startStr)
                ->whereDate('work_date', '<=', $endStr)
                ->get();
            $presensiMap = []; // [spg_id][Y-m-d] => BankPresensiDaily
            foreach ($presensiLogs as $pLog) {
                $presensiMap[$pLog->spg_id][substr((string)$pLog->work_date, 0, 10)] = $pLog;
            }

            if ($task->isSalary()) {
                // Ensure date multipliers exist
                foreach ($dates as $d) {
                    $dStr = $d->format('Y-m-d');
                    $isSunday = $d->dayOfWeek === Carbon::SUNDAY;
                    $existingMult = TaskDateMultiplier::where('payroll_task_id', $task->id)
                        ->whereDate('date', $dStr)
                        ->first();
                    if (!$existingMult) {
                        TaskDateMultiplier::create([
                            'payroll_task_id' => $task->id,
                            'date' => $dStr,
                            'multiplier' => $isSunday ? 2 : 1,
                            'note' => $isSunday ? 'BESAR' : null,
                        ]);
                    }
                }

                $syncedRecords = 0;
                foreach ($allSpgs as $spg) {
                    $record = SalaryRecord::firstOrCreate(
                        [
                            'payroll_task_id' => $task->id,
                            'spg_id' => $spg->id,
                        ],
                        [
                            'reference_outlet_id' => null,
                            'total_hari_masuk' => 0,
                            'attendance_percentage' => 0,
                            'uang_makan_utama' => 0,
                            'uang_makan_tambahan' => 0,
                            'base_salary_outlet' => 0,
                            'base_salary_recommended' => 0,
                            'policy_100_percent_override' => false,
                            'base_salary_final' => 0,
                            'uang_pulsa' => 0,
                            'uang_tambahan' => 0,
                            'total_salary' => 0,
                        ]
                    );

                    foreach ($dates as $d) {
                        $dStr = $d->format('Y-m-d');
                        $existingLog = SalaryDailyLog::where('salary_record_id', $record->id)
                            ->whereDate('date', $dStr)
                            ->first();

                        if ($existingLog && $preserveManualOverrides && $existingLog->is_manual_override) {
                            continue;
                        }

                        $bankItem = $presensiMap[$spg->id][$dStr] ?? null;
                        $outlet = ($bankItem && $bankItem->outlet_id) ? ($outletsById[$bankItem->outlet_id] ?? null) : null;
                        $isPresent = $outlet !== null;
                        $code = $outlet ? $outlet->outlet_code : null;

                        if ($existingLog) {
                            $existingLog->update([
                                'raw_cell_value' => $code,
                                'is_present' => $isPresent,
                                'matched_outlet_codes' => $code ? [$code] : [],
                                'chosen_outlet_id' => $outlet ? $outlet->id : null,
                                'outlet_code' => $code,
                                'custom_nominal_override' => $preserveManualOverrides ? $existingLog->custom_nominal_override : null,
                                'is_manual_override' => false,
                            ]);
                        } else {
                            SalaryDailyLog::create([
                                'salary_record_id' => $record->id,
                                'date' => $dStr,
                                'raw_cell_value' => $code,
                                'is_present' => $isPresent,
                                'matched_outlet_codes' => $code ? [$code] : [],
                                'chosen_outlet_id' => $outlet ? $outlet->id : null,
                                'outlet_code' => $code,
                                'multiplier' => 1,
                                'daily_uang_makan_utama' => 0,
                                'daily_uang_makan_tambahan' => 0,
                                'custom_nominal_override' => null,
                                'is_manual_override' => false,
                            ]);
                        }
                    }

                    $this->salaryCalc->calculateRecord($record->fresh(), true);
                    $syncedRecords++;
                }

                return ['synced_records' => $syncedRecords];
            } else {
                // INSENTIF: Strict 100% Full Payout Rule for Shared Outlets
                if ($task->twin_outlet_mode !== 'full') {
                    $task->update(['twin_outlet_mode' => 'full']);
                }

                $omzetLogs = BankOmzetDaily::whereDate('omzet_date', '>=', $startStr)
                    ->whereDate('omzet_date', '<=', $endStr)
                    ->get();
                $omzetMap = []; // [outlet_id][Y-m-d] => BankOmzetDaily
                foreach ($omzetLogs as $oLog) {
                    $omzetMap[$oLog->outlet_id][substr((string)$oLog->omzet_date, 0, 10)] = $oLog;
                }

                $settings = IncentiveSetting::current();
                $syncedRecords = 0;

                foreach ($allSpgs as $spg) {
                    $record = IncentiveRecord::firstOrCreate(
                        [
                            'payroll_task_id' => $task->id,
                            'spg_id' => $spg->id,
                        ],
                        [
                            'reference_outlet_id' => null,
                            'total_omzet_amk' => 0,
                            'total_omzet_amkopi' => 0,
                            'total_omzet' => 0,
                            'insentif_tier_1' => 0,
                            'insentif_tier_2' => 0,
                            'insentif_murni' => 0,
                            'uang_tambahan' => 0,
                            'total_insentif' => 0,
                        ]
                    );

                    foreach ($dates as $d) {
                        $dStr = $d->format('Y-m-d');
                        $existingLog = IncentiveDailyLog::where('incentive_record_id', $record->id)
                            ->whereDate('date', $dStr)
                            ->first();

                        if ($existingLog && $preserveManualOverrides && $existingLog->is_manual_override) {
                            continue;
                        }

                        $bankPresensi = $presensiMap[$spg->id][$dStr] ?? null;
                        $outlet = ($bankPresensi && $bankPresensi->outlet_id) ? ($outletsById[$bankPresensi->outlet_id] ?? null) : null;

                        $amk = 0.0;
                        $kopi = 0.0;
                        if ($outlet) {
                            $bankOmzet = $omzetMap[$outlet->id][$dStr] ?? null;
                            if ($bankOmzet) {
                                // 100% Full Payout Rule for Shared Outlets
                                $amk = (float)$bankOmzet->omzet_amk;
                                $kopi = (float)$bankOmzet->omzet_amkopi;
                            }
                        }

                        if ($existingLog) {
                            $existingLog->update([
                                'outlet_id' => $outlet ? $outlet->id : null,
                                'outlet_code' => $outlet ? $outlet->outlet_code : null,
                                'raw_outlet_amk' => $amk,
                                'raw_outlet_kopi' => $kopi,
                                'omzet_amk' => $amk,
                                'omzet_amkopi' => $kopi,
                                'total_daily_omzet' => $amk + $kopi,
                                'is_manual_override' => false,
                            ]);
                        } else {
                            IncentiveDailyLog::create([
                                'incentive_record_id' => $record->id,
                                'date' => $dStr,
                                'outlet_id' => $outlet ? $outlet->id : null,
                                'outlet_code' => $outlet ? $outlet->outlet_code : null,
                                'raw_outlet_amk' => $amk,
                                'raw_outlet_kopi' => $kopi,
                                'omzet_amk' => $amk,
                                'omzet_amkopi' => $kopi,
                                'total_daily_omzet' => $amk + $kopi,
                                'is_manual_override' => false,
                            ]);
                        }
                    }

                    $this->incentiveCalc->calculateRecord($record->fresh(), $settings, true);
                    $syncedRecords++;
                }

                return ['synced_records' => $syncedRecords];
            }
        });
    }

    /**
     * Automatically sync any DRAFT tasks whose date range overlaps with the newly uploaded month.
     */
    public function autoSyncDependentDraftTasks(int $year, int $month): void
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
        $monthEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

        $draftTasks = PayrollTask::where('status', 'DRAFT')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_date', [$monthStart, $monthEnd])
                  ->orWhereBetween('end_date', [$monthStart, $monthEnd]);
            })
            ->get();

        foreach ($draftTasks as $task) {
            $this->syncTaskFromBankData($task, true);
        }
    }

    protected function parseNumeric($val): float
    {
        if ($val === null || $val === '') {
            return 0.0;
        }
        if (is_int($val) || is_float($val)) {
            return max(0.0, (float)$val);
        }
        $clean = trim((string)$val);
        if ($clean === '' || $clean === '-' || strtoupper($clean) === 'OFF') {
            return 0.0;
        }
        $clean = preg_replace('/^(Rp\.?\s*)/i', '', $clean);
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $clean)) {
            $clean = str_replace('.', '', $clean);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+$/', $clean)) {
            $clean = str_replace(',', '', $clean);
        } else {
            $clean = preg_replace('/[^0-9.]/', '', $clean);
        }
        return is_numeric($clean) ? max(0.0, (float)$clean) : 0.0;
    }
}
