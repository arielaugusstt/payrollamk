<?php

namespace App\Http\Controllers;

use App\Models\IncentiveDailyLog;
use App\Models\IncentiveRecord;
use App\Models\IncentiveSetting;
use App\Models\Outlet;
use App\Models\OutletAlias;
use App\Models\PayrollTask;
use App\Models\SalaryDailyLog;
use App\Models\SalaryRecord;
use App\Models\Spg;
use App\Services\ExcelMatrixParserService;
use App\Services\IncentiveCalculatorService;
use App\Services\SalaryCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    protected ExcelMatrixParserService $parser;
    protected SalaryCalculatorService $salaryCalc;
    protected IncentiveCalculatorService $incentiveCalc;

    public function __construct(
        ExcelMatrixParserService $parser,
        SalaryCalculatorService $salaryCalc,
        IncentiveCalculatorService $incentiveCalc
    ) {
        $this->parser = $parser;
        $this->salaryCalc = $salaryCalc;
        $this->incentiveCalc = $incentiveCalc;
    }

    /**
     * Handle Excel upload and automatically commit into matrix (No manual mapping needed).
     */
    public function upload(Request $request, PayrollTask $task)
    {
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $file = $request->file('excel_file');
        $storedPath = $file->storeAs('uploads', 'task_' . $task->id . '_' . time() . '.' . $file->getClientOriginalExtension());
        $fullPath = Storage::path($storedPath);

        $analysis = $this->parser->analyzeFile($fullPath, $task);

        // Process 100% automatic import & mapping
        $this->autoCommitAnalysis($task, $analysis);

        // Cleanup temporary uploaded file
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        return redirect()->route('tasks.show', $task->id)
            ->with('success', 'File Excel berhasil diproses dan diimpor secara otomatis ke dalam matriks!');
    }

    /**
     * Show Pre-Calculation Validation & Anomaly Resolution Panel (Optional / Fallback).
     */
    public function showValidation(PayrollTask $task)
    {
        $analysis = session('upload_analysis_' . $task->id);
        if (!$analysis) {
            return redirect()->route('tasks.show', $task->id)->with('error', 'Tidak ada data upload aktif. Silakan upload file kembali.');
        }

        $allOutlets = Outlet::where('is_active', true)->get();
        $allSpgs = Spg::where('is_active', true)->with(['defaultOutlet', 'assignedOutlet'])->get();

        return view('tasks.validation', compact('task', 'analysis', 'allOutlets', 'allSpgs'));
    }

    /**
     * Commit validated data into the task matrix manually if validation panel is submitted.
     */
    public function commitValidation(Request $request, PayrollTask $task)
    {
        $analysis = session('upload_analysis_' . $task->id);
        if (!$analysis) {
            return redirect()->route('tasks.show', $task->id)->with('error', 'Sesi validasi telah berakhir. Silakan upload ulang.');
        }

        $spgMappings = $request->input('spg_mappings', []);
        $newSpgOutlets = $request->input('new_spg_outlets', []);

        foreach ($spgMappings as $rawName => $choice) {
            if ($choice === 'new') {
                $outletId = $newSpgOutlets[$rawName] ?? null;
                Spg::firstOrCreate(
                    ['spg_name' => strtoupper(trim($rawName))],
                    ['default_outlet_id' => $outletId, 'assigned_outlet_id' => $outletId, 'is_active' => true]
                );
            }
        }

        $this->autoCommitAnalysis($task, $analysis, $spgMappings);

        session()->forget(['upload_analysis_' . $task->id, 'upload_file_path_' . $task->id]);

        return redirect()->route('tasks.show', $task->id)->with('success', 'Data Excel berhasil disimpan ke dalam matriks!');
    }

    /**
     * Fully automated analysis commit engine.
     */
    protected function autoCommitAnalysis(PayrollTask $task, array $analysis, array $spgMappings = []): void
    {
        $allOutlets = Outlet::all();
        $outletsMap = $allOutlets->keyBy(fn($o) => strtoupper($o->outlet_code));
        $allAliases = OutletAlias::with('outlet')->get();
        $aliasMap = [];
        foreach ($allAliases as $al) {
            $aliasMap[strtolower(trim($al->alias))] = $al->outlet;
        }

        $allSpgs = Spg::with(['defaultOutlet', 'assignedOutlet'])->get();
        $spgMap = [];
        foreach ($allSpgs as $s) {
            $spgMap[strtolower(trim($s->spg_name))] = $s;
            if ($s->aliases && is_array($s->aliases)) {
                foreach ($s->aliases as $al) {
                    $spgMap[strtolower(trim($al))] = $s;
                }
            }
        }

        $dates = $task->getDatesList();

        if ($task->isSalary()) {
            $presensiRows = $analysis['presensi_rows'] ?? [];

            foreach ($presensiRows as $row) {
                $rawName = trim($row['raw_name'] ?? '');
                $spgName = trim($row['spg_name'] ?? '');

                if ($spgName === '' && $rawName === '') {
                    continue;
                }

                $lookupName = $spgName ?: $rawName;
                $spg = $spgMap[strtolower($lookupName)] ?? null;

                if (!$spg && isset($spgMappings[$rawName]) && is_numeric($spgMappings[$rawName])) {
                    $spg = Spg::find($spgMappings[$rawName]);
                }

                // If not found in database, auto-create as new Master SPG
                if (!$spg) {
                    $cleanName = strtoupper($lookupName);
                    $spg = Spg::firstOrCreate(
                        ['spg_name' => $cleanName],
                        [
                            'default_outlet_id' => $row['outlet_id'] ?? null,
                            'assigned_outlet_id' => $row['outlet_id'] ?? null,
                            'is_active' => true,
                            'aliases' => [strtolower($cleanName)],
                        ]
                    );
                    $spgMap[strtolower($lookupName)] = $spg;
                    $spgMap[strtolower($cleanName)] = $spg;
                }

                $outletId = $row['outlet_id'] ?? $spg->default_outlet_id ?? $spg->assigned_outlet_id;
                $record = SalaryRecord::firstOrCreate(
                    ['payroll_task_id' => $task->id, 'spg_id' => $spg->id],
                    [
                        'assigned_outlet_id' => $outletId,
                        'reference_outlet_id' => $outletId,
                        'policy_100_percent_override' => false
                    ]
                );

                $multipliersMap = $task->dateMultipliers->keyBy(fn($m) => $m->date->format('Y-m-d'));

                foreach ($dates as $d) {
                    $ymd = $d->format('Y-m-d');
                    $dayVal = $row['daily_values'][$ymd] ?? null;

                    if (is_array($dayVal)) {
                        $rawVal = $dayVal['raw_cell_value'] ?? '-';
                        $isPresent = (bool)($dayVal['is_present'] ?? false);
                        $chosenOutletId = $dayVal['outlet_id'] ?? null;
                        $outletCode = $dayVal['outlet_code'] ?? null;
                        $mult = (int)($dayVal['multiplier'] ?? 1);
                        $dailyUtama = (float)($dayVal['daily_uang_makan_utama'] ?? 0);
                        $dailyTambahan = (float)($dayVal['daily_uang_makan_tambahan'] ?? 0);
                    } else {
                        $rawVal = is_string($dayVal) ? $dayVal : '-';
                        $resolved = $this->salaryCalc->resolveCellPresenceAndOutlet($rawVal, $spg->defaultOutlet ?? $spg->assignedOutlet, $outletsMap, $aliasMap);
                        $multModel = $multipliersMap->get($ymd);
                        $mult = $multModel ? (int)$multModel->multiplier : 1;
                        $isPresent = $resolved['is_present'];
                        $chosenOutletId = $resolved['chosen_outlet_id'];
                        $outletCode = $resolved['outlet_code'];
                        $dailyUtama = 0;
                        $dailyTambahan = 0;
                    }

                    $logData = [
                        'raw_cell_value' => $rawVal,
                        'is_present' => $isPresent,
                        'matched_outlet_codes' => $outletCode ? [$outletCode] : [],
                        'chosen_outlet_id' => $chosenOutletId,
                        'outlet_code' => $outletCode,
                        'multiplier' => $mult,
                        'daily_uang_makan_utama' => $dailyUtama,
                        'daily_uang_makan_tambahan' => $dailyTambahan,
                    ];

                    $existingLog = SalaryDailyLog::where('salary_record_id', $record->id)
                        ->whereDate('date', $ymd)
                        ->first();

                    if ($existingLog) {
                        $existingLog->update($logData);
                    } else {
                        SalaryDailyLog::create(array_merge($logData, [
                            'salary_record_id' => $record->id,
                            'date' => $ymd,
                        ]));
                    }
                }

                $record->refresh();
                $this->salaryCalc->calculateRecord($record, true);
            }
        } else {
            $omzetRows = $analysis['omzet_rows'] ?? [];
            $settings = IncentiveSetting::current();

            $mainRows = [];
            $kopiRows = [];
            foreach ($omzetRows as $r) {
                if ($r['is_kopi_row']) {
                    $kopiRows[] = $r;
                } else {
                    $mainRows[] = $r;
                }
            }

            foreach ($mainRows as $row) {
                $rawName = trim($row['raw_name'] ?? '');
                $spgName = trim($row['spg_name'] ?? '');

                if ($spgName === '' && $rawName === '') {
                    continue;
                }

                $lookupName = $spgName ?: $rawName;
                $spg = $spgMap[strtolower($lookupName)] ?? null;

                if (!$spg && isset($spgMappings[$rawName]) && is_numeric($spgMappings[$rawName])) {
                    $spg = Spg::find($spgMappings[$rawName]);
                }

                // Auto-create new SPG if not existing
                if (!$spg) {
                    $cleanName = strtoupper($lookupName);
                    $spg = Spg::firstOrCreate(
                        ['spg_name' => $cleanName],
                        [
                            'default_outlet_id' => $row['outlet_id'] ?? null,
                            'assigned_outlet_id' => $row['outlet_id'] ?? null,
                            'is_active' => true,
                            'aliases' => [strtolower($cleanName)],
                        ]
                    );
                    $spgMap[strtolower($lookupName)] = $spg;
                    $spgMap[strtolower($cleanName)] = $spg;
                }

                $outletId = $row['outlet_id'] ?? $spg->default_outlet_id ?? $spg->assigned_outlet_id;
                $record = IncentiveRecord::firstOrCreate(
                    ['payroll_task_id' => $task->id, 'spg_id' => $spg->id],
                    [
                        'assigned_outlet_id' => $outletId,
                        'reference_outlet_id' => $outletId,
                        'uang_tambahan' => 0
                    ]
                );

                // Find corresponding coffee row if any
                $matchedKopiRow = null;
                foreach ($kopiRows as $k) {
                    if (($k['spg_id'] && $k['spg_id'] === $spg->id) || ($k['outlet_id'] && in_array($k['outlet_id'], array_filter([$spg->default_outlet_id, $spg->assigned_outlet_id])))) {
                        $matchedKopiRow = $k;
                        break;
                    }
                }

                $details = $row['daily_details'] ?? [];

                foreach ($dates as $d) {
                    $ymd = $d->format('Y-m-d');
                    $dayDetail = $details[$ymd] ?? null;

                    if ($dayDetail) {
                        $rawAmk = (float)($dayDetail['raw_outlet_amk'] ?? $dayDetail['omzet_amk'] ?? 0);
                        $rawKopi = (float)($dayDetail['raw_outlet_kopi'] ?? $dayDetail['omzet_amkopi'] ?? 0);
                        $valAmk = (float)($dayDetail['omzet_amk'] ?? 0);
                        $valKopi = (float)($dayDetail['omzet_amkopi'] ?? 0);
                        $outId = $dayDetail['outlet_id'] ?? null;
                        $outCode = $dayDetail['outlet_code'] ?? null;
                    } else {
                        $valAmk = (float)($row['daily_amk'][$ymd] ?? $row['daily_values'][$ymd] ?? 0);
                        $valKopi = (float)($row['daily_kopi'][$ymd] ?? ($matchedKopiRow ? ($matchedKopiRow['daily_values'][$ymd] ?? 0) : 0));
                        $rawAmk = $valAmk;
                        $rawKopi = $valKopi;
                        $outId = $row['outlet_id'] ?? null;
                        $outCode = $row['outlet_code'] ?? null;
                    }

                    $logData = [
                        'outlet_id' => $outId,
                        'outlet_code' => $outCode,
                        'raw_outlet_amk' => $rawAmk,
                        'raw_outlet_kopi' => $rawKopi,
                        'omzet_amk' => $valAmk,
                        'omzet_amkopi' => $valKopi,
                        'total_daily_omzet' => $valAmk + $valKopi,
                    ];

                    $existingLog = IncentiveDailyLog::where('incentive_record_id', $record->id)
                        ->whereDate('date', $ymd)
                        ->first();

                    if ($existingLog) {
                        $existingLog->update($logData);
                    } else {
                        IncentiveDailyLog::create(array_merge($logData, [
                            'incentive_record_id' => $record->id,
                            'date' => $ymd,
                        ]));
                    }
                }

                $record->refresh();
                $this->incentiveCalc->calculateRecord($record, $settings, true);
            }
        }
    }
}
