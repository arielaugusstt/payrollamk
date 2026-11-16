<?php

namespace App\Http\Controllers;

use App\Models\IncentiveDailyLog;
use App\Models\IncentiveRecord;
use App\Models\Outlet;
use App\Models\PayrollTask;
use App\Models\SalaryDailyLog;
use App\Models\SalaryRecord;
use App\Models\Spg;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear = (int)$request->query('year', 2026);
        $componentView = $request->query('component', 'combined');
        $outletFilter = $request->query('outlet_id');
        $spgFilter = $request->query('spg_ids', []);

        if (is_string($spgFilter) && !empty($spgFilter)) {
            $spgFilter = explode(',', $spgFilter);
        }

        $availableYears = PayrollTask::select('period_year')->distinct()->orderBy('period_year', 'desc')->pluck('period_year');
        if ($availableYears->isEmpty()) {
            $availableYears = collect([2026, 2027]);
        }

        $allOutlets = Outlet::orderBy('outlet_code')->get();
        $allSpgs = Spg::with('defaultOutlet')->orderBy('spg_name')->get();

        // Tasks for this year with FINISHED status
        $tasks = PayrollTask::where('period_year', $selectedYear)
            ->where('status', 'FINISHED')
            ->get();

        $salaryTasks = $tasks->where('task_type', 'SALARY')->keyBy('period_month');
        $incentiveTasks = $tasks->where('task_type', 'INSENTIF')->keyBy('period_month');

        $salaryTaskIds = $salaryTasks->pluck('id');
        $incentiveTaskIds = $incentiveTasks->pluck('id');

        $salaryRecords = SalaryRecord::with(['dailyLogs.chosenOutlet', 'referenceOutlet'])->whereIn('payroll_task_id', $salaryTaskIds)->get()->groupBy('payroll_task_id');
        $incentiveRecords = IncentiveRecord::with(['dailyLogs.outlet', 'referenceOutlet'])->whereIn('payroll_task_id', $incentiveTaskIds)->get()->groupBy('payroll_task_id');

        // If filtered by Outlet, find SPGs who actually worked at that outlet
        $eligibleSpgIds = null;
        if ($outletFilter) {
            $salSpgs = SalaryDailyLog::whereHas('salaryRecord', fn($q) => $q->whereIn('payroll_task_id', $salaryTaskIds))
                ->where('is_present', true)
                ->where('chosen_outlet_id', $outletFilter)
                ->with('salaryRecord')
                ->get()
                ->pluck('salaryRecord.spg_id');

            $incSpgs = IncentiveDailyLog::whereHas('incentiveRecord', fn($q) => $q->whereIn('payroll_task_id', $incentiveTaskIds))
                ->where('outlet_id', $outletFilter)
                ->where('total_daily_omzet', '>', 0)
                ->with('incentiveRecord')
                ->get()
                ->pluck('incentiveRecord.spg_id');

            $eligibleSpgIds = $salSpgs->merge($incSpgs)->unique()->values()->all();
        }

        $targetSpgs = $allSpgs;
        if ($eligibleSpgIds !== null) {
            $targetSpgs = $targetSpgs->whereIn('id', $eligibleSpgIds);
        }
        if (!empty($spgFilter)) {
            $targetSpgs = $targetSpgs->whereIn('id', $spgFilter);
        }

        // Build monthly company expenses
        $monthlySalaryExpense = array_fill(1, 12, 0.0);
        $monthlyIncentiveExpense = array_fill(1, 12, 0.0);
        $monthlyCombinedExpense = array_fill(1, 12, 0.0);

        for ($m = 1; $m <= 12; $m++) {
            if ($sTask = $salaryTasks->get($m)) {
                $recs = $salaryRecords->get($sTask->id) ?? collect();
                if ($outletFilter) {
                    $recs = $recs->filter(function ($rec) use ($outletFilter) {
                        return $rec->dailyLogs->contains(fn($l) => $l->is_present && $l->chosen_outlet_id == $outletFilter);
                    });
                }
                if (!empty($spgFilter)) {
                    $recs = $recs->whereIn('spg_id', $spgFilter);
                }
                $monthlySalaryExpense[$m] = $recs->sum('total_salary');
            }

            if ($iTask = $incentiveTasks->get($m)) {
                $recs = $incentiveRecords->get($iTask->id) ?? collect();
                if ($outletFilter) {
                    $recs = $recs->filter(function ($rec) use ($outletFilter) {
                        return $rec->dailyLogs->contains(fn($l) => $l->outlet_id == $outletFilter && $l->total_daily_omzet > 0);
                    });
                }
                if (!empty($spgFilter)) {
                    $recs = $recs->whereIn('spg_id', $spgFilter);
                }
                $monthlyIncentiveExpense[$m] = $recs->sum('total_insentif');
            }

            $monthlyCombinedExpense[$m] = $monthlySalaryExpense[$m] + $monthlyIncentiveExpense[$m];
        }

        $cumulativeSalaryTotal = array_sum($monthlySalaryExpense);
        $cumulativeIncentiveTotal = array_sum($monthlyIncentiveExpense);
        $cumulativeGrandTotal = array_sum($monthlyCombinedExpense);

        // Build Annual SPG Earnings Matrix
        $spgMatrixRows = [];

        foreach ($targetSpgs as $spg) {
            $rowMonths = array_fill(1, 12, 0.0);
            $annualSpgTotal = 0.0;
            $allVisitedCodes = [];

            for ($m = 1; $m <= 12; $m++) {
                $salVal = 0.0;
                $incVal = 0.0;

                if ($sTask = $salaryTasks->get($m)) {
                    $sRecs = $salaryRecords->get($sTask->id);
                    $rec = $sRecs ? $sRecs->firstWhere('spg_id', $spg->id) : null;
                    if ($rec) {
                        $salVal = (float)$rec->total_salary;
                        if ($rec->outlets_summary && is_array($rec->outlets_summary)) {
                            foreach ($rec->outlets_summary as $os) {
                                $allVisitedCodes[$os['code']] = ($allVisitedCodes[$os['code']] ?? 0) + ($os['days'] ?? 0);
                            }
                        }
                    }
                }

                if ($iTask = $incentiveTasks->get($m)) {
                    $iRecs = $incentiveRecords->get($iTask->id);
                    $rec = $iRecs ? $iRecs->firstWhere('spg_id', $spg->id) : null;
                    if ($rec) {
                        $incVal = (float)$rec->total_insentif;
                    }
                }

                $cellVal = match ($componentView) {
                    'salary' => $salVal,
                    'insentif' => $incVal,
                    default => $salVal + $incVal,
                };

                $rowMonths[$m] = $cellVal;
                $annualSpgTotal += $cellVal;
            }

            $visitedSummaryStr = '-';
            if (!empty($allVisitedCodes)) {
                arsort($allVisitedCodes);
                $parts = [];
                foreach ($allVisitedCodes as $c => $d) {
                    $parts[] = "{$c} ({$d}d)";
                }
                $visitedSummaryStr = implode(', ', $parts);
            } elseif ($spg->defaultOutlet) {
                $visitedSummaryStr = $spg->defaultOutlet->outlet_code;
            }

            $spgMatrixRows[] = [
                'spg_id' => $spg->id,
                'spg_name' => $spg->spg_name,
                'outlet_code' => $visitedSummaryStr,
                'months' => $rowMonths,
                'annual_total' => $annualSpgTotal,
            ];
        }

        $monthlyTotals = match ($componentView) {
            'salary' => $monthlySalaryExpense,
            'insentif' => $monthlyIncentiveExpense,
            default => $monthlyCombinedExpense,
        };

        return view('dashboard.index', compact(
            'selectedYear',
            'availableYears',
            'componentView',
            'outletFilter',
            'spgFilter',
            'allOutlets',
            'allSpgs',
            'targetSpgs',
            'monthlySalaryExpense',
            'monthlyIncentiveExpense',
            'monthlyCombinedExpense',
            'monthlyTotals',
            'cumulativeSalaryTotal',
            'cumulativeIncentiveTotal',
            'cumulativeGrandTotal',
            'spgMatrixRows'
        ));
    }
}
