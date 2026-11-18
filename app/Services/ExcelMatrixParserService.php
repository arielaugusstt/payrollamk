<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletAlias;
use App\Models\PayrollTask;
use App\Models\Spg;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelMatrixParserService
{
    protected SalaryCalculatorService $salaryCalculator;
    protected IncentiveCalculatorService $incentiveCalculator;

    public function __construct(
        SalaryCalculatorService $salaryCalculator,
        IncentiveCalculatorService $incentiveCalculator
    ) {
        $this->salaryCalculator = $salaryCalculator;
        $this->incentiveCalculator = $incentiveCalculator;
    }

    /**
     * Inspect and parse uploaded Excel/CSV file for a given task.
     * Strictly supports the official BAKU 3D Matrix structure (presensi.xlsx):
     * - Sheet 1 (Presensi): SPG names across columns (Row 1), Dates 1..31 down (Col A), Cells = Outlet Codes.
     * - Sheet 2 (Omzet): Dates 1..31 across columns (Row 1), Outlets & AMKopi down (Col A), Cells = Daily Sales.
     * - 3D Relational Mapping: (Date d, Outlet O, SPG s) -> Daily Salary & Daily Omzet (AMK + AMKopi).
     */
    public function analyzeFile(string $filePath, PayrollTask $task): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheetNames = $spreadsheet->getSheetNames();

        // Load all Master Outlets and Aliases
        $allOutlets = Outlet::all();
        $outletsMap = $allOutlets->keyBy(fn($o) => strtoupper(trim($o->outlet_code)));
        $allAliases = OutletAlias::with('outlet')->get();
        $aliasMap = [];
        foreach ($allAliases as $alias) {
            $aliasMap[strtolower(trim($alias->alias))] = $alias->outlet;
        }

        // Load all Master SPGs
        $allSpgs = Spg::with(['defaultOutlet', 'assignedOutlet'])->get();
        $spgMap = [];
        foreach ($allSpgs as $spg) {
            $spgMap[strtolower(trim($spg->spg_name))] = $spg;
            if ($spg->aliases && is_array($spg->aliases)) {
                foreach ($spg->aliases as $al) {
                    $spgMap[strtolower(trim($al))] = $spg;
                }
            }
        }

        // Task Date Mapping (handles both 21-20 Salary and 1-31 Incentive)
        $taskDates = $task->getDatesList();
        $dateHeaders = array_map(fn($d) => $d->format('Y-m-d'), $taskDates);
        $dateByDayNum = [];
        foreach ($taskDates as $tDate) {
            $dateByDayNum[(int)$tDate->format('j')] = $tDate->format('Y-m-d');
        }

        $anomalies = [
            'unrecognized_outlets' => [],
            'unrecognized_spgs' => [],
            'dirty_numbers' => [],
            'multi_codes' => [],
            'amk_amkopi_pairs' => [],
        ];

        // 1. Separate Presensi and Omzet Sheets / Sections
        $presensiRowsData = null;
        $omzetRowsData = null;

        if (count($sheetNames) >= 2) {
            // Multi-sheet workbook (presensi.xlsx: Sheet1 = Presensi, omset = Omzet)
            foreach ($sheetNames as $name) {
                $lower = strtolower(trim($name));
                if (str_contains($lower, 'presensi') || str_contains($lower, 'absen') || str_contains($lower, 'kehadiran')) {
                    $presensiRowsData = $spreadsheet->getSheetByName($name)->toArray(null, true, true, true);
                }
                if (str_contains($lower, 'omzet') || str_contains($lower, 'omset') || str_contains($lower, 'penjualan') || str_contains($lower, 'sales')) {
                    $omzetRowsData = $spreadsheet->getSheetByName($name)->toArray(null, true, true, true);
                }
            }
            if (!$presensiRowsData) {
                $presensiRowsData = $spreadsheet->getSheet(0)->toArray(null, true, true, true);
            }
            if (!$omzetRowsData && count($sheetNames) > 1) {
                $omzetRowsData = $spreadsheet->getSheet(1)->toArray(null, true, true, true);
            }
        } else {
            // Single sheet or CSV: Detect whether it's Presensi, Omzet, or combined
            $firstSheetName = strtolower(trim($sheetNames[0] ?? ''));
            $allRows = $spreadsheet->getSheet(0)->toArray(null, true, true, true);
            $firstRow = reset($allRows) ?: [];
            $firstRowNumericCount = 0;
            foreach (array_slice($firstRow, 1, 15) as $cVal) {
                if ($cVal !== null && is_numeric(trim((string)$cVal))) {
                    $firstRowNumericCount++;
                }
            }

            if (str_contains($firstSheetName, 'omset') || str_contains($firstSheetName, 'omzet') || $firstRowNumericCount >= 8) {
                $omzetRowsData = $allRows;
            } else {
                $omzetHeaderIdx = null;
                foreach ($allRows as $rIdx => $rowCells) {
                    if ($rIdx <= 2) continue;
                    $numCount = 0;
                    $sampleCells = array_slice($rowCells, 1, 15);
                    foreach ($sampleCells as $cVal) {
                        if ($cVal !== null && is_numeric(trim((string)$cVal))) {
                            $numCount++;
                        }
                    }
                    if ($numCount >= 8) {
                        $omzetHeaderIdx = $rIdx;
                        break;
                    }
                }

                if ($omzetHeaderIdx !== null) {
                    $presensiRowsData = array_slice($allRows, 0, $omzetHeaderIdx - 1, true);
                    $omzetRowsData = array_slice($allRows, $omzetHeaderIdx - 1, null, true);
                } else {
                    $presensiRowsData = $allRows;
                }
            }
        }

        // 2. Parse Presensi Matrix (Sheet 1)
        // Orientation: Row 1 = SPG Names (Col B..AO), Col A = Dates 1..31, Cells = Outlet Codes
        $presensiParsed = $this->parsePresensiMatrix(
            $presensiRowsData ?? [],
            $taskDates,
            $dateByDayNum,
            $outletsMap,
            $aliasMap,
            $allOutlets,
            $spgMap,
            $anomalies
        );

        // 3. Parse Omzet Matrix (Sheet 2)
        // Orientation: Row 1 = Dates 1..31 (Col B..AF), Col A = Outlet Codes / AMKopi, Cells = Rupiah Omzet
        $omzetParsed = $this->parseOmzetMatrix(
            $omzetRowsData ?? [],
            $taskDates,
            $dateByDayNum,
            $outletsMap,
            $aliasMap,
            $allOutlets,
            $anomalies
        );

        // 4. Perform 3D Relational Mapping (PER TANGGAL -> PER OUTLET -> PER SPG)
        $mappedResult = $this->build3DRelationalMap(
            $task,
            $taskDates,
            $presensiParsed,
            $omzetParsed,
            $outletsMap,
            $aliasMap,
            $allOutlets
        );

        // 5. Build Outlet Daily Verification Grid (Per Tanggal Per Outlet View)
        $verificationGrid = $this->buildVerificationGrid(
            $taskDates,
            $allOutlets,
            $presensiParsed['outlet_guards'],
            $omzetParsed['omzet_amk'],
            $omzetParsed['omzet_kopi']
        );

        return [
            'sheet_names' => $sheetNames,
            'task_id' => $task->id,
            'task_type' => $task->task_type,
            'twin_outlet_mode' => $task->twin_outlet_mode ?: 'full',
            'dates' => $dateHeaders,
            'anomalies' => $anomalies,
            'presensi_rows' => $mappedResult['salary_rows'],
            'omzet_rows' => $mappedResult['incentive_rows'],
            'verification_grid' => $verificationGrid,
            'all_outlets' => $allOutlets,
        ];
    }

    /**
     * Parse Sheet 1: Presensi Matrix.
     */
    protected function parsePresensiMatrix(
        array $rows,
        array $taskDates,
        array $dateByDayNum,
        $outletsMap,
        array $aliasMap,
        $allOutlets,
        array &$spgMap,
        array &$anomalies
    ): array {
        if (empty($rows)) {
            return ['spgs' => [], 'attendance' => [], 'outlet_guards' => []];
        }

        $headerRow = reset($rows);
        $rawColumns = []; // colKey => name

        foreach ($headerRow as $colKey => $cellVal) {
            $name = trim((string)$cellVal);
            if ($name === '' || is_numeric($name) || in_array(strtoupper($name), ['NO', 'NO.', 'TGL', 'TANGGAL', 'DATE', 'DAY'])) {
                continue;
            }
            $rawColumns[$colKey] = $name;
        }

        $dataRows = array_slice($rows, 1, null, true);

        // Count attendance per column to handle duplicates (e.g. SHELA appearing twice)
        $colAttendanceCount = [];
        $colDayCells = []; // colKey => [dayNum => rawVal]

        foreach ($dataRows as $rIdx => $rowCells) {
            $firstColVal = trim((string)($rowCells['A'] ?? ''));
            if ($firstColVal === '' || !is_numeric($firstColVal)) {
                $secondColVal = trim((string)($rowCells['B'] ?? ''));
                if (is_numeric($secondColVal)) {
                    $dayNum = (int)$secondColVal;
                } else {
                    continue;
                }
            } else {
                $dayNum = (int)$firstColVal;
            }

            if ($dayNum < 1 || $dayNum > 31) {
                continue;
            }

            foreach ($rawColumns as $colKey => $colName) {
                $cellVal = trim((string)($rowCells[$colKey] ?? ''));
                $colDayCells[$colKey][$dayNum] = $cellVal;

                if ($cellVal !== '' && $cellVal !== '-' && !in_array(strtoupper($cellVal), ['OFF', 'LIBUR', 'X', '0'])) {
                    $colAttendanceCount[$colKey] = ($colAttendanceCount[$colKey] ?? 0) + 1;
                }
            }
        }

        // Group columns by name
        $nameToCols = [];
        foreach ($rawColumns as $colKey => $colName) {
            $nameToCols[strtoupper($colName)][] = $colKey;
        }

        $validCols = []; // colKey => uniqueSpgName
        foreach ($nameToCols as $upperName => $colKeys) {
            if (count($colKeys) === 1) {
                $validCols[$colKeys[0]] = $upperName;
            } else {
                // If a duplicate column has 0 attendance, ignore it
                $activeKeys = array_filter($colKeys, fn($k) => ($colAttendanceCount[$k] ?? 0) > 0);
                if (count($activeKeys) === 1) {
                    $validCols[reset($activeKeys)] = $upperName;
                } elseif (count($activeKeys) > 1) {
                    $idx = 1;
                    foreach ($activeKeys as $k) {
                        $validCols[$k] = ($idx === 1) ? $upperName : "{$upperName} ({$idx})";
                        $idx++;
                    }
                } else {
                    // Both 0 attendance: keep first
                    $validCols[$colKeys[0]] = $upperName;
                }
            }
        }

        // Auto-Register / Sync SPGs into Master SPG table (only if already in Master SPG or has > 0 attendance)
        $spgModels = []; // spgName => Spg model
        foreach ($validCols as $colKey => $spgName) {
            $lowerName = strtolower(trim($spgName));
            $spg = $spgMap[$lowerName] ?? null;
            $attCount = $colAttendanceCount[$colKey] ?? 0;

            if (!$spg) {
                if ($attCount === 0) {
                    // Gracefully accept 0-attendance columns without polluting Master SPGs
                    continue;
                }
                $cleanName = strtoupper(trim($spgName));
                $spg = Spg::firstOrCreate(
                    ['spg_name' => $cleanName],
                    [
                        'default_outlet_id' => null,
                        'assigned_outlet_id' => null,
                        'is_active' => true,
                        'aliases' => [strtolower($cleanName)],
                    ]
                );
                $spgMap[$lowerName] = $spg;
                $spgMap[strtolower($cleanName)] = $spg;
            }
            $spgModels[$spgName] = $spg;
        }

        // Build attendance map & outlet guards index
        // attendanceMap: [spgName => [dayNum => ['raw' => ..., 'outlet' => OutletModel, 'code' => '...']]]
        // outletGuardsMap: [dayNum => [outletCode => [spgName, ...]]]
        $attendanceMap = [];
        $outletGuardsMap = [];

        for ($d = 1; $d <= 31; $d++) {
            $outletGuardsMap[$d] = [];
            foreach ($validCols as $colKey => $spgName) {
                if (!isset($spgModels[$spgName])) {
                    continue;
                }
                $rawVal = $colDayCells[$colKey][$d] ?? '';
                $resolvedOutlet = $this->resolveOutletString($rawVal, $outletsMap, $aliasMap, $allOutlets);

                if ($resolvedOutlet) {
                    $outCode = $resolvedOutlet->outlet_code;
                    $attendanceMap[$spgName][$d] = [
                        'raw' => $rawVal,
                        'outlet' => $resolvedOutlet,
                        'code' => $outCode,
                        'is_present' => true,
                    ];
                    $outletGuardsMap[$d][$outCode][] = $spgName;
                } else {
                    $attendanceMap[$spgName][$d] = [
                        'raw' => $rawVal ?: '-',
                        'outlet' => null,
                        'code' => null,
                        'is_present' => false,
                    ];
                }
            }
        }

        return [
            'spgs' => $spgModels,
            'attendance' => $attendanceMap,
            'outlet_guards' => $outletGuardsMap,
        ];
    }

    /**
     * Parse Sheet 2: Omzet Matrix.
     */
    protected function parseOmzetMatrix(
        array $rows,
        array $taskDates,
        array $dateByDayNum,
        $outletsMap,
        array $aliasMap,
        $allOutlets,
        array &$anomalies
    ): array {
        if (empty($rows)) {
            return ['omzet_amk' => [], 'omzet_kopi' => []];
        }

        $headerRow = reset($rows);
        $dateCols = []; // colKey => dayNum (1..31)

        foreach ($headerRow as $colKey => $cellVal) {
            $val = trim((string)$cellVal);
            if (is_numeric($val)) {
                $dayNum = (int)$val;
                if ($dayNum >= 1 && $dayNum <= 31) {
                    $dateCols[$colKey] = $dayNum;
                }
            }
        }

        $dataRows = array_slice($rows, 1, null, true);
        $omzetAmk = [];  // [outletCode => [dayNum => float]]
        $omzetKopi = []; // [outletCode => [dayNum => float]]

        foreach ($dataRows as $rIdx => $rowCells) {
            $rowLabel = trim((string)($rowCells['A'] ?? $rowCells['B'] ?? ''));
            if ($rowLabel === '' || is_numeric($rowLabel)) {
                continue;
            }

            $upperLabel = strtoupper($rowLabel);
            if (in_array($upperLabel, ['GREEN TEA', 'THAI TEA', 'CHOCO', 'PTT', 'DONUTS', '0', 'TOTAL', 'JUMLAH', 'GRAND TOTAL'])) {
                continue;
            }

            $isKopi = (bool)(stripos($rowLabel, 'kopi') !== false || stripos($rowLabel, 'amkopi') !== false);
            $cleanLabel = preg_replace('/^(amkopi|am kopi|kopi)\s*/i', '', $rowLabel);
            $cleanLabel = trim($cleanLabel);

            $resolvedOutlet = $this->resolveOutletString($cleanLabel, $outletsMap, $aliasMap, $allOutlets);
            if (!$resolvedOutlet) {
                continue;
            }

            $outCode = $resolvedOutlet->outlet_code;

            foreach ($dateCols as $colKey => $dayNum) {
                $rawVal = $rowCells[$colKey] ?? 0;
                $amount = $this->normalizeNumericCell($rawVal, $anomalies, $rowLabel, "Day $dayNum");

                // CRITICAL RULE: SUM matching outlet rows (e.g. delta Row 45 + Row 104)
                if ($isKopi) {
                    $omzetKopi[$outCode][$dayNum] = ($omzetKopi[$outCode][$dayNum] ?? 0.0) + $amount;
                } else {
                    $omzetAmk[$outCode][$dayNum] = ($omzetAmk[$outCode][$dayNum] ?? 0.0) + $amount;
                }
            }
        }

        return [
            'omzet_amk' => $omzetAmk,
            'omzet_kopi' => $omzetKopi,
        ];
    }

    /**
     * Perform the Core 3D Relational Mapping: (Date d, Outlet O, SPG s).
     */
    protected function build3DRelationalMap(
        PayrollTask $task,
        array $taskDates,
        array $presensiParsed,
        array $omzetParsed,
        $outletsMap,
        array $aliasMap,
        $allOutlets
    ): array {
        $spgModels = $presensiParsed['spgs'] ?? [];
        $attendance = $presensiParsed['attendance'] ?? [];
        $outletGuards = $presensiParsed['outlet_guards'] ?? [];

        $omzetAmk = $omzetParsed['omzet_amk'] ?? [];
        $omzetKopi = $omzetParsed['omzet_kopi'] ?? [];

        $multipliersMap = $task->dateMultipliers->keyBy(fn($m) => $m->date->format('Y-m-d'));
        $twinOutletMode = $task->twin_outlet_mode ?: 'full';

        $salaryRows = [];
        $incentiveRows = [];
        $rIdx = 1;

        foreach ($spgModels as $spgName => $spg) {
            $dailySalaryValues = [];
            $dailyIncentiveValues = [];
            $outletVisitsCount = []; // outlet_id => count
            $totalHariMasuk = 0;

            foreach ($taskDates as $tDate) {
                $ymd = $tDate->format('Y-m-d');
                $dayNum = (int)$tDate->format('j');

                $att = $attendance[$spgName][$dayNum] ?? null;
                $isPresent = ($att && $att['is_present'] && $att['outlet']);

                $multModel = $multipliersMap->get($ymd);
                $mult = $multModel ? (int)$multModel->multiplier : ($tDate->isSunday() ? 2 : 1);

                if ($isPresent) {
                    $totalHariMasuk++;
                    $outlet = $att['outlet'];
                    $outCode = $outlet->outlet_code;
                    $outId = $outlet->id;

                    $outletVisitsCount[$outId] = ($outletVisitsCount[$outId] ?? 0) + 1;

                    // Daily Salary Allowance strictly from that date's outlet
                    $utamaRate = (float)$outlet->uang_makan_utama;
                    $tambahanRate = (float)$outlet->uang_makan_tambahan;
                    $dailyUtama = $utamaRate * $mult;
                    $dailyTambahan = $tambahanRate * $mult;

                    $dailySalaryValues[$ymd] = [
                        'raw_cell_value' => $att['raw'],
                        'is_present' => true,
                        'outlet_code' => $outCode,
                        'outlet_id' => $outId,
                        'multiplier' => $mult,
                        'daily_uang_makan_utama' => $dailyUtama,
                        'daily_uang_makan_tambahan' => $dailyTambahan,
                        'daily_total_uang_makan' => $dailyUtama + $dailyTambahan,
                    ];

                    // Daily Omzet from that date's outlet
                    $rawOutletAmk = $omzetAmk[$outCode][$dayNum] ?? 0.0;
                    $rawOutletKopi = $omzetKopi[$outCode][$dayNum] ?? 0.0;
                    $guards = $outletGuards[$dayNum][$outCode] ?? [$spgName];
                    $guardCount = count($guards);

                    if ($twinOutletMode === 'full') {
                        $spgAmk = $rawOutletAmk;
                        $spgKopi = $rawOutletKopi;
                    } else {
                        // Split mode: Divide equally among SPGs on duty at that outlet on that date
                        $spgAmk = ($guardCount > 0) ? ($rawOutletAmk / $guardCount) : $rawOutletAmk;
                        $spgKopi = ($guardCount > 0) ? ($rawOutletKopi / $guardCount) : $rawOutletKopi;
                    }

                    $dailyIncentiveValues[$ymd] = [
                        'outlet_code' => $outCode,
                        'outlet_id' => $outId,
                        'raw_outlet_amk' => $rawOutletAmk,
                        'raw_outlet_kopi' => $rawOutletKopi,
                        'omzet_amk' => $spgAmk,
                        'omzet_amkopi' => $spgKopi,
                        'total_daily_omzet' => $spgAmk + $spgKopi,
                        'guards_count' => $guardCount,
                    ];
                } else {
                    $dailySalaryValues[$ymd] = [
                        'raw_cell_value' => '-',
                        'is_present' => false,
                        'outlet_code' => null,
                        'outlet_id' => null,
                        'multiplier' => $mult,
                        'daily_uang_makan_utama' => 0.0,
                        'daily_uang_makan_tambahan' => 0.0,
                        'daily_total_uang_makan' => 0.0,
                    ];

                    $dailyIncentiveValues[$ymd] = [
                        'outlet_code' => null,
                        'outlet_id' => null,
                        'raw_outlet_amk' => 0.0,
                        'raw_outlet_kopi' => 0.0,
                        'omzet_amk' => 0.0,
                        'omzet_amkopi' => 0.0,
                        'total_daily_omzet' => 0.0,
                        'guards_count' => 0,
                    ];
                }
            }

            // Determine Dominant Outlet for Gaji Pokok
            $dominantOutlet = null;
            if (!empty($outletVisitsCount)) {
                arsort($outletVisitsCount);
                $domId = array_key_first($outletVisitsCount);
                $dominantOutlet = $allOutlets->firstWhere('id', $domId);
            }

            $domOutletId = $dominantOutlet ? $dominantOutlet->id : ($spg->default_outlet_id ?? $spg->assigned_outlet_id);
            $domOutletCode = $dominantOutlet ? $dominantOutlet->outlet_code : ($spg->defaultOutlet ? $spg->defaultOutlet->outlet_code : '-');

            // Salary Row
            $salaryRows[] = [
                'row_index' => $rIdx,
                'raw_name' => $spgName,
                'spg_id' => $spg->id,
                'spg_name' => $spg->spg_name,
                'outlet_id' => $domOutletId,
                'outlet_code' => $domOutletCode,
                'is_kopi_row' => false,
                'daily_values' => $dailySalaryValues,
                'total_hari_masuk' => $totalHariMasuk,
            ];

            // Incentive Rows (Main + Kopi for backward compatibility & split storage)
            $dailyAmkList = [];
            $dailyKopiList = [];
            $dailyTotalList = [];

            foreach ($dailyIncentiveValues as $ymd => $vals) {
                $dailyAmkList[$ymd] = $vals['omzet_amk'];
                $dailyKopiList[$ymd] = $vals['omzet_amkopi'];
                $dailyTotalList[$ymd] = $vals['total_daily_omzet'];
            }

            // Main Omzet Row
            $incentiveRows[] = [
                'row_index' => $rIdx++,
                'raw_name' => $spgName,
                'spg_id' => $spg->id,
                'spg_name' => $spg->spg_name,
                'outlet_id' => $domOutletId,
                'outlet_code' => $domOutletCode,
                'is_kopi_row' => false,
                'daily_values' => $dailyTotalList,
                'daily_amk' => $dailyAmkList,
                'daily_kopi' => $dailyKopiList,
                'daily_details' => $dailyIncentiveValues,
            ];

            // Kopi Omzet Row
            $incentiveRows[] = [
                'row_index' => $rIdx++,
                'raw_name' => 'AMKopi ' . $spgName,
                'spg_id' => $spg->id,
                'spg_name' => $spg->spg_name,
                'outlet_id' => $domOutletId,
                'outlet_code' => $domOutletCode,
                'is_kopi_row' => true,
                'daily_values' => $dailyKopiList,
                'daily_amk' => $dailyAmkList,
                'daily_kopi' => $dailyKopiList,
                'daily_details' => $dailyIncentiveValues,
            ];
        }

        return [
            'salary_rows' => $salaryRows,
            'incentive_rows' => $incentiveRows,
        ];
    }

    /**
     * Build Outlet Daily Verification Grid (Per Tanggal Per Outlet View).
     */
    protected function buildVerificationGrid(
        array $taskDates,
        $allOutlets,
        array $outletGuards,
        array $omzetAmk,
        array $omzetKopi
    ): array {
        $grid = [];

        foreach ($allOutlets as $outlet) {
            $outCode = $outlet->outlet_code;
            $hasAnyOmzet = false;
            $hasAnyGuard = false;
            $dailyData = [];

            foreach ($taskDates as $tDate) {
                $ymd = $tDate->format('Y-m-d');
                $dayNum = (int)$tDate->format('j');

                $amk = $omzetAmk[$outCode][$dayNum] ?? 0.0;
                $kopi = $omzetKopi[$outCode][$dayNum] ?? 0.0;
                $total = $amk + $kopi;
                $spgs = $outletGuards[$dayNum][$outCode] ?? [];
                $spgCount = count($spgs);

                if ($total > 0) $hasAnyOmzet = true;
                if ($spgCount > 0) $hasAnyGuard = true;

                $status = 'idle';
                if ($total > 0 && $spgCount > 0) {
                    $status = 'ok';
                } elseif ($total > 0 && $spgCount === 0) {
                    $status = 'no_spg'; // Alert: Omzet without SPG
                } elseif ($total === 0.0 && $spgCount > 0) {
                    $status = 'no_omzet'; // Info: SPG on duty but 0 omzet
                }

                $dailyData[$ymd] = [
                    'day_num' => $dayNum,
                    'omzet_amk' => $amk,
                    'omzet_kopi' => $kopi,
                    'total_omzet' => $total,
                    'spgs' => $spgs,
                    'spg_count' => $spgCount,
                    'status' => $status,
                ];
            }

            // Only display outlets active in this period or with data
            if ($hasAnyOmzet || $hasAnyGuard || $outlet->is_active) {
                $grid[] = [
                    'outlet_id' => $outlet->id,
                    'outlet_code' => $outCode,
                    'outlet_name' => $outlet->outlet_name,
                    'has_omzet' => $hasAnyOmzet,
                    'has_guards' => $hasAnyGuard,
                    'days' => $dailyData,
                ];
            }
        }

        // Sort: Outlets with omzet first, then by code
        usort($grid, function($a, $b) {
            if ($a['has_omzet'] !== $b['has_omzet']) {
                return $b['has_omzet'] <=> $a['has_omzet'];
            }
            return strcmp($a['outlet_code'], $b['outlet_code']);
        });

        return $grid;
    }

    /**
     * Resolve raw cell string to official Master Outlet.
     */
    public function resolveOutletString(?string $raw, $outletsMap, array $aliasMap, $allOutlets): ?Outlet
    {
        if ($raw === null) {
            return null;
        }

        $clean = trim($raw);
        if ($clean === '' || $clean === '-' || in_array(strtoupper($clean), ['OFF', 'LIBUR', 'X', '0', 'NAN'])) {
            return null;
        }

        // 1. Direct match by outlet_code uppercase
        $upper = strtoupper($clean);
        if (isset($outletsMap[$upper])) {
            return $outletsMap[$upper];
        }

        // 2. Direct match by alias lowercase
        $lower = strtolower($clean);
        if (isset($aliasMap[$lower])) {
            return $aliasMap[$lower];
        }

        // 3. Normalized regex replacements (e.g. "tp 2" -> "TP-2", "pa 1" -> "PA-1")
        $hyphenated = preg_replace('/\s+/', '-', $upper);
        if (isset($outletsMap[$hyphenated])) {
            return $outletsMap[$hyphenated];
        }

        // 4. Fuzzy fallback matching
        return $this->findFuzzyOutlet($clean, $allOutlets);
    }

    /**
     * Fuzzy matching helper for outlets.
     */
    protected function findFuzzyOutlet(string $rawName, $allOutlets): ?Outlet
    {
        $clean = strtolower(preg_replace('/[^a-z0-9]/', '', $rawName));
        if ($clean === '') return null;

        $bestMatch = null;
        $bestDist = 999;

        foreach ($allOutlets as $outlet) {
            $code = strtolower(preg_replace('/[^a-z0-9]/', '', $outlet->outlet_code));
            $name = strtolower(preg_replace('/[^a-z0-9]/', '', $outlet->outlet_name));

            if ($code === $clean || $name === $clean) {
                return $outlet;
            }

            $dist = levenshtein($clean, $code);
            if ($dist < $bestDist && $dist <= 2) {
                $bestDist = $dist;
                $bestMatch = $outlet;
            }
        }

        return $bestMatch;
    }

    /**
     * Clean and normalize numeric cell values.
     */
    protected function normalizeNumericCell($cellVal, array &$anomalies, string $rowName, string $dateStr): float
    {
        if ($cellVal === null || $cellVal === '' || $cellVal === '-') {
            return 0.0;
        }

        if (is_numeric($cellVal)) {
            return (float)$cellVal;
        }

        $str = trim((string)$cellVal);
        $cleanStr = preg_replace('/[^\d.,-]/', '', $str);

        if (str_contains($cleanStr, '.') && str_contains($cleanStr, ',')) {
            $cleanStr = str_replace('.', '', $cleanStr);
            $cleanStr = str_replace(',', '.', $cleanStr);
        } elseif (str_contains($cleanStr, '.')) {
            $parts = explode('.', $cleanStr);
            if (count($parts) > 1 && strlen(end($parts)) === 3) {
                $cleanStr = str_replace('.', '', $cleanStr);
            }
        } elseif (str_contains($cleanStr, ',')) {
            $parts = explode(',', $cleanStr);
            if (count($parts) > 1 && strlen(end($parts)) === 3) {
                $cleanStr = str_replace(',', '', $cleanStr);
            } else {
                $cleanStr = str_replace(',', '.', $cleanStr);
            }
        }

        if (!is_numeric($cleanStr)) {
            $anomalies['dirty_numbers'][] = [
                'raw_name' => $rowName,
                'date' => $dateStr,
                'raw_value' => $str,
                'resolved_value' => 0.0,
            ];
            return 0.0;
        }

        return (float)$cleanStr;
    }
}
