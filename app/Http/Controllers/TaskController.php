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
use App\Models\TaskDateMultiplier;
use App\Services\IncentiveCalculatorService;
use App\Services\SalaryCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    protected SalaryCalculatorService $salaryCalc;
    protected IncentiveCalculatorService $incentiveCalc;

    public function __construct(
        SalaryCalculatorService $salaryCalc,
        IncentiveCalculatorService $incentiveCalc
    ) {
        $this->salaryCalc = $salaryCalc;
        $this->incentiveCalc = $incentiveCalc;
    }

    /**
     * Module 1: Task Hub (Homepage)
     */
    public function index(Request $request)
    {
        $typeFilter = $request->query('type');
        $statusFilter = $request->query('status');
        $yearFilter = $request->query('year');

        $query = PayrollTask::query()->withCount([
            'salaryRecords',
            'incentiveRecords'
        ])->orderBy('period_year', 'desc')->orderBy('period_month', 'desc');

        if ($typeFilter) {
            $query->where('task_type', $typeFilter);
        }
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }
        if ($yearFilter) {
            $query->where('period_year', $yearFilter);
        }

        $tasks = $query->get();

        $totalDraft = $tasks->where('status', 'DRAFT')->count();
        $totalFinished = $tasks->where('status', 'FINISHED')->count();
        $totalSalaryTasks = $tasks->where('task_type', 'SALARY')->count();
        $totalIncentiveTasks = $tasks->where('task_type', 'INSENTIF')->count();

        $availableYears = PayrollTask::select('period_year')->distinct()->orderBy('period_year', 'desc')->pluck('period_year');
        if ($availableYears->isEmpty()) {
            $availableYears = collect([2026, 2027]);
        }

        return view('tasks.index', compact(
            'tasks',
            'totalDraft',
            'totalFinished',
            'totalSalaryTasks',
            'totalIncentiveTasks',
            'availableYears',
            'typeFilter',
            'statusFilter',
            'yearFilter'
        ));
    }

    /**
     * Create New Task with automatic working period mapping.
     */
    public function store(Request $request)
    {
        $request->validate([
            'task_type' => ['required', 'in:SALARY,INSENTIF'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2035'],
            'notes' => ['nullable', 'string'],
        ]);

        $taskType = $request->task_type;
        $month = (int)$request->period_month;
        $year = (int)$request->period_year;

        $periodInfo = PayrollTask::calculateWorkingPeriod($taskType, $month, $year);
        $title = 'Payroll ' . ($taskType === 'SALARY' ? 'Salary' : 'Insentif') . ' ' . $periodInfo['period_label'];

        $existing = PayrollTask::where('task_type', $taskType)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->first();

        if ($existing) {
            return redirect()->route('tasks.show', $existing->id)
                ->with('info', 'Task untuk periode tersebut sudah ada. Membuka task yang sudah tersedia.');
        }

        $task = PayrollTask::create([
            'task_type' => $taskType,
            'title' => $title,
            'period_month' => $month,
            'period_year' => $year,
            'period_label' => $periodInfo['period_label'],
            'start_date' => $periodInfo['start_date'],
            'end_date' => $periodInfo['end_date'],
            'status' => 'DRAFT',
            'notes' => $request->notes,
        ]);

        $dates = $task->getDatesList();

        if ($task->isSalary()) {
            foreach ($dates as $d) {
                $isSunday = $d->isSunday();
                TaskDateMultiplier::create([
                    'payroll_task_id' => $task->id,
                    'date' => $d->format('Y-m-d'),
                    'multiplier' => $isSunday ? 2 : 1,
                    'note' => $isSunday ? 'BESAR' : null,
                ]);
            }
        }

        $activeSpgs = Spg::where('is_active', true)->with(['defaultOutlet', 'assignedOutlet'])->get();

        if ($task->isSalary()) {
            foreach ($activeSpgs as $spg) {
                $outletId = $spg->default_outlet_id ?? $spg->assigned_outlet_id;
                $rec = SalaryRecord::create([
                    'payroll_task_id' => $task->id,
                    'spg_id' => $spg->id,
                    'assigned_outlet_id' => $outletId,
                    'reference_outlet_id' => $outletId,
                    'policy_100_percent_override' => false,
                ]);

                foreach ($dates as $d) {
                    $isSunday = $d->isSunday();
                    SalaryDailyLog::create([
                        'salary_record_id' => $rec->id,
                        'date' => $d->format('Y-m-d'),
                        'raw_cell_value' => '-',
                        'is_present' => false,
                        'matched_outlet_codes' => [],
                        'chosen_outlet_id' => null,
                        'outlet_code' => null,
                        'multiplier' => $isSunday ? 2 : 1,
                        'daily_uang_makan_utama' => 0,
                        'daily_uang_makan_tambahan' => 0,
                    ]);
                }

                $this->salaryCalc->calculateRecord($rec, true);
            }
        } else {
            $settings = IncentiveSetting::current();
            foreach ($activeSpgs as $spg) {
                $outletId = $spg->default_outlet_id ?? $spg->assigned_outlet_id;
                $rec = IncentiveRecord::create([
                    'payroll_task_id' => $task->id,
                    'spg_id' => $spg->id,
                    'assigned_outlet_id' => $outletId,
                    'reference_outlet_id' => $outletId,
                    'uang_tambahan' => 0,
                ]);

                foreach ($dates as $d) {
                    IncentiveDailyLog::create([
                        'incentive_record_id' => $rec->id,
                        'date' => $d->format('Y-m-d'),
                        'outlet_id' => null,
                        'outlet_code' => null,
                        'omzet_amk' => 0,
                        'omzet_amkopi' => 0,
                        'total_daily_omzet' => 0,
                    ]);
                }

                $this->incentiveCalc->calculateRecord($rec, $settings, true);
            }
        }

        return redirect()->route('tasks.show', $task->id)->with('success', 'Task berhasil dibuat!');
    }

    /**
     * Show interactive horizontal pivot matrix grid.
     */
    public function show(PayrollTask $task)
    {
        $dates = $task->getDatesList();
        $allOutlets = Outlet::where('is_active', true)->orderBy('outlet_code')->get();

        if ($task->isSalary()) {
            $task->load([
                'salaryRecords.spg.defaultOutlet',
                'salaryRecords.spg.assignedOutlet',
                'salaryRecords.referenceOutlet',
                'salaryRecords.assignedOutlet',
                'salaryRecords.dailyLogs.chosenOutlet',
                'dateMultipliers'
            ]);
            $multipliersMap = $task->dateMultipliers->keyBy(fn($m) => $m->date->format('Y-m-d'));

            $grandTotals = [
                'total_hari_masuk' => $task->salaryRecords->sum('total_hari_masuk'),
                'uang_makan_utama' => $task->salaryRecords->sum('uang_makan_utama'),
                'uang_makan_tambahan' => $task->salaryRecords->sum('uang_makan_tambahan'),
                'base_salary_final' => $task->salaryRecords->sum('base_salary_final'),
                'uang_pulsa' => $task->salaryRecords->sum('uang_pulsa'),
                'uang_tambahan' => $task->salaryRecords->sum('uang_tambahan'),
                'total_salary' => $task->salaryRecords->sum('total_salary'),
            ];

            $verificationGrid = $this->buildVerificationGrid($task, $allOutlets, $dates);

            return view('tasks.show_salary', compact('task', 'dates', 'multipliersMap', 'grandTotals', 'allOutlets', 'verificationGrid'));
        } else {
            $task->load([
                'incentiveRecords.spg.defaultOutlet',
                'incentiveRecords.spg.assignedOutlet',
                'incentiveRecords.referenceOutlet',
                'incentiveRecords.assignedOutlet',
                'incentiveRecords.dailyLogs.outlet'
            ]);

            $grandTotals = [
                'total_omzet_amk' => $task->incentiveRecords->sum('total_omzet_amk'),
                'total_omzet_amkopi' => $task->incentiveRecords->sum('total_omzet_amkopi'),
                'total_omzet' => $task->incentiveRecords->sum('total_omzet'),
                'insentif_tier_1' => $task->incentiveRecords->sum('insentif_tier_1'),
                'insentif_tier_2' => $task->incentiveRecords->sum('insentif_tier_2'),
                'insentif_murni' => $task->incentiveRecords->sum('insentif_murni'),
                'uang_tambahan' => $task->incentiveRecords->sum('uang_tambahan'),
                'total_insentif' => $task->incentiveRecords->sum('total_insentif'),
            ];

            $verificationGrid = $this->buildVerificationGrid($task, $allOutlets, $dates);

            return view('tasks.show_incentive', compact('task', 'dates', 'grandTotals', 'allOutlets', 'verificationGrid'));
        }
    }

    /**
     * Inline update a cell in the horizontal matrix (AJAX).
     * Dynamic: SPG can work at any outlet on any date.
     */
    public function updateCell(Request $request, PayrollTask $task)
    {
        if ($task->isFinished()) {
            return response()->json(['error' => 'Task is finished and locked. Re-open task to make edits.'], 403);
        }

        $dateStr = $request->input('date');
        $recordId = (int)$request->input('record_id');

        if ($task->isSalary()) {
            $record = SalaryRecord::where('payroll_task_id', $task->id)->findOrFail($recordId);
            $log = SalaryDailyLog::where('salary_record_id', $record->id)
                ->where('date', $dateStr)
                ->firstOrFail();

            $rawValue = $request->input('raw_value');
            $outletsMap = Outlet::all()->keyBy(fn($o) => strtoupper($o->outlet_code));
            $allAliases = OutletAlias::with('outlet')->get();
            $aliasMap = [];
            foreach ($allAliases as $al) {
                $aliasMap[strtolower(trim($al->alias))] = $al->outlet;
            }

            // Resolve dynamic outlet for that specific date
            $fallbackOutlet = $record->referenceOutlet ?? ($record->spg ? $record->spg->defaultOutlet : null);
            $resolved = $this->salaryCalc->resolveCellPresenceAndOutlet($rawValue, $fallbackOutlet, $outletsMap, $aliasMap);

            $log->raw_cell_value = $rawValue;
            $log->is_present = $resolved['is_present'];
            $log->matched_outlet_codes = $resolved['matched_codes'];
            $log->chosen_outlet_id = $resolved['chosen_outlet_id'];
            $log->outlet_code = $resolved['outlet_code'];
            $log->save();

            $this->salaryCalc->calculateRecord($record, true);

            return response()->json([
                'success' => true,
                'record' => $record->fresh(['referenceOutlet', 'dailyLogs.chosenOutlet']),
                'log' => $log->fresh(['chosenOutlet']),
                'outlets_summary' => $record->outlets_summary,
            ]);
        } else {
            $record = IncentiveRecord::where('payroll_task_id', $task->id)->findOrFail($recordId);
            $log = IncentiveDailyLog::where('incentive_record_id', $record->id)
                ->where('date', $dateStr)
                ->firstOrFail();

            if ($request->has('omzet_amk')) {
                $log->omzet_amk = (float)$request->input('omzet_amk');
            }
            if ($request->has('omzet_amkopi')) {
                $log->omzet_amkopi = (float)$request->input('omzet_amkopi');
            }
            if ($request->has('total_daily_omzet')) {
                $total = (float)$request->input('total_daily_omzet');
                $log->omzet_amk = $total;
                $log->omzet_amkopi = 0;
            }
            if ($request->has('outlet_code')) {
                $code = strtoupper(trim((string)$request->input('outlet_code')));
                $out = Outlet::where('outlet_code', $code)->first();
                $log->outlet_id = $out ? $out->id : null;
                $log->outlet_code = $code;
            }

            $log->total_daily_omzet = $log->omzet_amk + $log->omzet_amkopi;
            $log->save();

            $this->incentiveCalc->calculateRecord($record, null, true);

            return response()->json([
                'success' => true,
                'record' => $record->fresh(['referenceOutlet', 'dailyLogs.outlet']),
                'log' => $log,
                'outlets_summary' => $record->outlets_summary,
            ]);
        }
    }

    /**
     * Override Reference Outlet for Gaji Pokok & Uang Pulsa resolution.
     */
    public function updateReferenceOutlet(Request $request, PayrollTask $task, SalaryRecord $record)
    {
        if ($task->isFinished()) {
            return response()->json(['error' => 'Task locked.'], 403);
        }

        $outletId = $request->input('outlet_id') ?? $request->input('reference_outlet_id');
        $record->reference_outlet_id = $outletId ?: null;
        $this->salaryCalc->calculateRecord($record, true);

        return response()->json([
            'success' => true,
            'record' => $record->fresh(['referenceOutlet']),
        ]);
    }

    /**
     * Update Date Multiplier (1x, 2x, 3x) for SALARY tasks.
     */
    public function updateMultiplier(Request $request, PayrollTask $task)
    {
        if ($task->isFinished()) {
            return response()->json(['error' => 'Task locked.'], 403);
        }

        $dateStr = $request->input('date');
        $multiplier = (int)$request->input('multiplier');
        $note = $request->input('note');

        $multModel = TaskDateMultiplier::updateOrCreate(
            ['payroll_task_id' => $task->id, 'date' => $dateStr],
            ['multiplier' => $multiplier, 'note' => $note]
        );

        foreach ($task->salaryRecords as $rec) {
            $this->salaryCalc->calculateRecord($rec, true);
        }

        return response()->json([
            'success' => true,
            'multiplier' => $multModel,
        ]);
    }

    /**
     * Auto-mark all Sundays as 2x multiplier ("BESAR") in 1 click!
     */
    public function autoMarkSundays(PayrollTask $task)
    {
        if ($task->isFinished()) {
            return back()->with('error', 'Task locked.');
        }

        $dates = $task->getDatesList();
        foreach ($dates as $d) {
            if ($d->isSunday()) {
                TaskDateMultiplier::updateOrCreate(
                    ['payroll_task_id' => $task->id, 'date' => $d->format('Y-m-d')],
                    ['multiplier' => 2, 'note' => 'BESAR']
                );
            }
        }

        foreach ($task->salaryRecords as $rec) {
            $this->salaryCalc->calculateRecord($rec, true);
        }

        return back()->with('success', 'Semua hari Minggu berhasil diset sebagai 2x (BESAR)!');
    }

    /**
     * Toggle Policy 100% Full Base Salary override.
     */
    public function togglePolicy(Request $request, PayrollTask $task, SalaryRecord $record)
    {
        if ($task->isFinished()) {
            return response()->json(['error' => 'Task locked.'], 403);
        }

        $record->policy_100_percent_override = (bool)$request->input('policy_override');
        $this->salaryCalc->calculateRecord($record, true);

        return response()->json([
            'success' => true,
            'record' => $record->fresh(),
        ]);
    }

    /**
     * Update manual additional money (Uang Tambahan).
     */
    public function updateManualAddition(Request $request, PayrollTask $task, $recordId)
    {
        if ($task->isFinished()) {
            return response()->json(['error' => 'Task locked.'], 403);
        }

        $val = (float)$request->input('uang_tambahan', 0);

        if ($task->isSalary()) {
            $record = SalaryRecord::where('payroll_task_id', $task->id)->findOrFail($recordId);
            $record->uang_tambahan = $val;
            $this->salaryCalc->calculateRecord($record, true);
        } else {
            $record = IncentiveRecord::where('payroll_task_id', $task->id)->findOrFail($recordId);
            $record->uang_tambahan = $val;
            $this->incentiveCalc->calculateRecord($record, null, true);
        }

        return response()->json([
            'success' => true,
            'record' => $record->fresh(),
        ]);
    }

    /**
     * Finish Task.
     */
    public function finish(PayrollTask $task)
    {
        $task->update([
            'status' => 'FINISHED',
            'finished_at' => now(),
        ]);

        return back()->with('success', 'Task berhasil diselesaikan (FINISHED) dan dikunci!');
    }

    /**
     * Re-open Task.
     */
    public function reopen(PayrollTask $task)
    {
        $task->update([
            'status' => 'DRAFT',
            'finished_at' => null,
        ]);

        return back()->with('success', 'Task berhasil dibuka kembali (DRAFT) untuk revisi.');
    }

    /**
     * Delete Task.
     */
    public function destroy(PayrollTask $task)
    {
        $task->delete();
        return redirect()->route('tasks.index')->with('success', 'Task berhasil dihapus.');
    }

    /**
     * Toggle Mode Omzet Outlet Kembar (Split vs Full).
     */
    public function toggleTwinOutletMode(Request $request, PayrollTask $task)
    {
        $mode = $request->input('twin_outlet_mode');
        if (!in_array($mode, ['split', 'full'])) {
            $mode = ($task->twin_outlet_mode === 'split') ? 'full' : 'split';
        }

        $task->update(['twin_outlet_mode' => $mode]);

        if ($task->isIncentive()) {
            $settings = IncentiveSetting::current();
            $records = $task->incentiveRecords()->with('dailyLogs')->get();
            $dates = $task->getDatesList();

            foreach ($dates as $d) {
                $ymd = $d->format('Y-m-d');
                $logsOnDate = [];

                foreach ($records as $r) {
                    $log = $r->dailyLogs->first(fn($l) => $l->date->format('Y-m-d') === $ymd);
                    if ($log && $log->outlet_code) {
                        $logsOnDate[$log->outlet_code][] = $log;
                    }
                }

                foreach ($logsOnDate as $outCode => $logsList) {
                    $guardCount = count($logsList);
                    if ($guardCount === 0) continue;

                    foreach ($logsList as $log) {
                        $rawAmk = (float)$log->raw_outlet_amk;
                        $rawKopi = (float)$log->raw_outlet_kopi;

                        if ($rawAmk == 0 && (float)$log->omzet_amk > 0) {
                            $rawAmk = (float)$log->omzet_amk * ($task->twin_outlet_mode === 'split' ? $guardCount : 1);
                        }
                        if ($rawKopi == 0 && (float)$log->omzet_amkopi > 0) {
                            $rawKopi = (float)$log->omzet_amkopi * ($task->twin_outlet_mode === 'split' ? $guardCount : 1);
                        }

                        if ($mode === 'full') {
                            $spgAmk = $rawAmk;
                            $spgKopi = $rawKopi;
                        } else {
                            $spgAmk = ($guardCount > 0) ? ($rawAmk / $guardCount) : $rawAmk;
                            $spgKopi = ($guardCount > 0) ? ($rawKopi / $guardCount) : $rawKopi;
                        }

                        $log->update([
                            'raw_outlet_amk' => $rawAmk,
                            'raw_outlet_kopi' => $rawKopi,
                            'omzet_amk' => $spgAmk,
                            'omzet_amkopi' => $spgKopi,
                            'total_daily_omzet' => $spgAmk + $spgKopi,
                        ]);
                    }
                }
            }

            foreach ($records as $rec) {
                $this->incentiveCalc->calculateRecord($rec->refresh(), $settings, true);
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'twin_outlet_mode' => $mode,
                'label' => ($mode === 'full' ? 'Full per SPG' : 'Bagi Rata')
            ]);
        }

        return redirect()->route('tasks.show', $task->id)->with('success', 'Mode Omzet Outlet Kembar berhasil diubah menjadi: ' . ($mode === 'full' ? 'Full per SPG' : 'Bagi Rata Jumlah SPG Jaga'));
    }

    /**
     * Build Verification Grid (Per Tanggal Per Outlet View).
     */
    protected function buildVerificationGrid(PayrollTask $task, $allOutlets, array $dates): array
    {
        $grid = [];

        if ($task->isSalary()) {
            $records = $task->salaryRecords()->with(['spg', 'dailyLogs.chosenOutlet'])->get();
            foreach ($allOutlets as $outlet) {
                $outCode = $outlet->outlet_code;
                $hasAnyPresence = false;
                $daysData = [];

                foreach ($dates as $d) {
                    $ymd = $d->format('Y-m-d');
                    $dayNum = (int)$d->format('j');
                    $spgs = [];
                    $dailyUangMakan = 0.0;

                    foreach ($records as $r) {
                        $log = $r->dailyLogs->first(fn($l) => $l->date->format('Y-m-d') === $ymd);
                        if ($log && $log->is_present && ($log->outlet_code === $outCode || $log->chosen_outlet_id === $outlet->id)) {
                            $spgs[] = $r->spg->spg_name;
                            $dailyUangMakan += ((float)$log->daily_uang_makan_utama + (float)$log->daily_uang_makan_tambahan);
                        }
                    }

                    $spgCount = count($spgs);
                    if ($spgCount > 0) $hasAnyPresence = true;

                    $daysData[$ymd] = [
                        'day_num' => $dayNum,
                        'spgs' => $spgs,
                        'spg_count' => $spgCount,
                        'daily_value' => $dailyUangMakan,
                        'status' => $spgCount > 0 ? 'ok' : 'idle',
                    ];
                }

                if ($hasAnyPresence || $outlet->is_active) {
                    $grid[] = [
                        'outlet' => $outlet,
                        'outlet_code' => $outCode,
                        'outlet_name' => $outlet->outlet_name,
                        'has_activity' => $hasAnyPresence,
                        'days' => $daysData,
                    ];
                }
            }
        } else {
            $records = $task->incentiveRecords()->with(['spg', 'dailyLogs'])->get();
            foreach ($allOutlets as $outlet) {
                $outCode = $outlet->outlet_code;
                $hasAnyActivity = false;
                $daysData = [];

                foreach ($dates as $d) {
                    $ymd = $d->format('Y-m-d');
                    $dayNum = (int)$d->format('j');
                    $spgs = [];
                    $totalAmk = 0.0;
                    $totalKopi = 0.0;

                    foreach ($records as $r) {
                        $log = $r->dailyLogs->first(fn($l) => $l->date->format('Y-m-d') === $ymd);
                        if ($log && ($log->outlet_code === $outCode || $log->outlet_id === $outlet->id)) {
                            $spgs[] = $r->spg->spg_name;
                            $totalAmk += (float)$log->omzet_amk;
                            $totalKopi += (float)$log->omzet_amkopi;
                        }
                    }

                    $totalOmzet = $totalAmk + $totalKopi;
                    $spgCount = count($spgs);

                    if ($totalOmzet > 0 || $spgCount > 0) {
                        $hasAnyActivity = true;
                    }

                    $status = 'idle';
                    if ($totalOmzet > 0 && $spgCount > 0) {
                        $status = 'ok';
                    } elseif ($totalOmzet > 0 && $spgCount === 0) {
                        $status = 'no_spg'; // Alert: Omzet without SPG
                    } elseif ($totalOmzet === 0.0 && $spgCount > 0) {
                        $status = 'no_omzet'; // SPG on duty but 0 omzet
                    }

                    $daysData[$ymd] = [
                        'day_num' => $dayNum,
                        'spgs' => $spgs,
                        'spg_count' => $spgCount,
                        'omzet_amk' => $totalAmk,
                        'omzet_kopi' => $totalKopi,
                        'total_omzet' => $totalOmzet,
                        'status' => $status,
                    ];
                }

                if ($hasAnyActivity || $outlet->is_active) {
                    $grid[] = [
                        'outlet' => $outlet,
                        'outlet_code' => $outCode,
                        'outlet_name' => $outlet->outlet_name,
                        'has_activity' => $hasAnyActivity,
                        'days' => $daysData,
                    ];
                }
            }
        }

        usort($grid, function($a, $b) {
            if ($a['has_activity'] !== $b['has_activity']) {
                return $b['has_activity'] <=> $a['has_activity'];
            }
            return strcmp($a['outlet_code'], $b['outlet_code']);
        });

        return $grid;
    }
}
