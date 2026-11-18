<?php

namespace App\Http\Controllers;

use App\Models\BankOmzetDaily;
use App\Models\BankPresensiDaily;
use App\Models\MonthlyDataPeriod;
use App\Models\Outlet;
use App\Models\Spg;
use App\Services\BankDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BankDataController extends Controller
{
    protected BankDataService $bankService;

    public function __construct(BankDataService $bankService)
    {
        $this->bankService = $bankService;
    }

    /**
     * Display the Bank Data Bulanan hub & interactive Preview/Edit matrices.
     */
    public function index(Request $request)
    {
        $year = (int)$request->query('year', 2026);
        $month = (int)$request->query('month', 11);
        if ($month < 1 || $month > 12) {
            $month = 11;
        }

        $period = MonthlyDataPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['has_presensi' => false, 'has_omzet' => false]
        );

        $allPeriodsInYear = MonthlyDataPeriod::where('year', $year)
            ->get()
            ->keyBy('month');

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $spgs = Spg::where('is_active', true)->orderBy('spg_name')->get();
        $outlets = Outlet::where('is_active', true)->orderBy('id')->get();
        $outletsById = $outlets->keyBy('id');

        // Load Presensi Matrix for selected (year, month)
        $presensiRows = BankPresensiDaily::where('period_id', $period->id)->get();
        $presensiGrid = []; // [spg_id][day] => ['outlet_id' => ?int, 'outlet_code' => ?string]
        $spgAttendanceTotals = [];
        foreach ($spgs as $spg) {
            $spgAttendanceTotals[$spg->id] = 0;
        }

        foreach ($presensiRows as $pRow) {
            $outObj = $pRow->outlet_id ? ($outletsById[$pRow->outlet_id] ?? null) : null;
            $code = $outObj ? $outObj->outlet_code : null;
            $presensiGrid[$pRow->spg_id][$pRow->day_number] = [
                'id' => $pRow->id,
                'outlet_id' => $pRow->outlet_id,
                'outlet_code' => $code,
            ];
            if ($code) {
                $spgAttendanceTotals[$pRow->spg_id] = ($spgAttendanceTotals[$pRow->spg_id] ?? 0) + 1;
            }
        }

        // Load Omzet Matrix for selected (year, month)
        $omzetRows = BankOmzetDaily::where('period_id', $period->id)->get();
        $omzetGrid = []; // [outlet_id][day] => ['amk' => float, 'kopi' => float, 'total' => float]
        $outletMonthlyTotals = [];
        foreach ($outlets as $out) {
            $outletMonthlyTotals[$out->id] = ['amk' => 0.0, 'kopi' => 0.0, 'total' => 0.0];
        }

        foreach ($omzetRows as $oRow) {
            $amk = (float)$oRow->omzet_amk;
            $kopi = (float)$oRow->omzet_amkopi;
            $tot = (float)$oRow->total_omzet;
            $omzetGrid[$oRow->outlet_id][$oRow->day_number] = [
                'id' => $oRow->id,
                'amk' => $amk,
                'kopi' => $kopi,
                'total' => $tot,
            ];
            if (isset($outletMonthlyTotals[$oRow->outlet_id])) {
                $outletMonthlyTotals[$oRow->outlet_id]['amk'] += $amk;
                $outletMonthlyTotals[$oRow->outlet_id]['kopi'] += $kopi;
                $outletMonthlyTotals[$oRow->outlet_id]['total'] += $tot;
            }
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('bank_data.index', compact(
            'year',
            'month',
            'monthNames',
            'period',
            'allPeriodsInYear',
            'daysInMonth',
            'spgs',
            'outlets',
            'presensiGrid',
            'spgAttendanceTotals',
            'omzetGrid',
            'outletMonthlyTotals'
        ));
    }

    /**
     * Slot 1: Upload Full-Month Presensi Excel (Dates 1..31).
     */
    public function uploadPresensi(Request $request)
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2035'],
            'month' => ['required', 'integer', 'between:1,12'],
            'presensi_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $year = (int)$request->year;
        $month = (int)$request->month;
        $file = $request->file('presensi_file');

        $result = $this->bankService->importPresensi(
            $file->getRealPath(),
            $year,
            $month,
            $file->getClientOriginalName()
        );

        $periodLabel = MonthlyDataPeriod::monthName($month) . ' ' . $year;

        return redirect()->route('bank-data.index', ['year' => $year, 'month' => $month, 'tab' => 'presensi'])
            ->with('success', "Presensi Bulanan {$periodLabel} berhasil diunggah! ({$result['active_spg_count']} SPG aktif, {$result['days_imported']} hari kalender tersimpan otomatis).");
    }

    /**
     * Slot 2: Upload Full-Month Omzet Excel (Dates 1..31).
     */
    public function uploadOmzet(Request $request)
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2035'],
            'month' => ['required', 'integer', 'between:1,12'],
            'omzet_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $year = (int)$request->year;
        $month = (int)$request->month;
        $file = $request->file('omzet_file');

        $result = $this->bankService->importOmzet(
            $file->getRealPath(),
            $year,
            $month,
            $file->getClientOriginalName()
        );

        $periodLabel = MonthlyDataPeriod::monthName($month) . ' ' . $year;
        $formattedTotal = number_format($result['grand_total_omzet'], 0, ',', '.');

        return redirect()->route('bank-data.index', ['year' => $year, 'month' => $month, 'tab' => 'omzet'])
            ->with('success', "Omzet Bulanan {$periodLabel} berhasil diunggah! ({$result['active_outlets_count']} outlet aktif, Total Omzet Rp {$formattedTotal}).");
    }

    /**
     * Delete uploaded Presensi data for a given Year & Month in Bank Data Bulanan.
     */
    public function deletePresensi(Request $request)
    {
        $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $year = (int)$request->year;
        $month = (int)$request->month;
        $period = MonthlyDataPeriod::where('year', $year)->where('month', $month)->first();

        if ($period) {
            BankPresensiDaily::where('period_id', $period->id)->delete();
            $period->update([
                'has_presensi' => false,
                'presensi_uploaded_at' => null,
                'presensi_filename' => null,
            ]);
            $this->bankService->autoSyncDependentDraftTasks($year, $month);
        }

        $periodLabel = MonthlyDataPeriod::monthName($month) . ' ' . $year;

        return redirect()->route('bank-data.index', ['year' => $year, 'month' => $month, 'tab' => 'presensi'])
            ->with('success', "Data Presensi Bulanan {$periodLabel} berhasil dihapus dan di-reset!");
    }

    /**
     * Delete uploaded Omzet data for a given Year & Month in Bank Data Bulanan.
     */
    public function deleteOmzet(Request $request)
    {
        $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $year = (int)$request->year;
        $month = (int)$request->month;
        $period = MonthlyDataPeriod::where('year', $year)->where('month', $month)->first();

        if ($period) {
            BankOmzetDaily::where('period_id', $period->id)->delete();
            $period->update([
                'has_omzet' => false,
                'omzet_uploaded_at' => null,
                'omzet_filename' => null,
            ]);
            $this->bankService->autoSyncDependentDraftTasks($year, $month);
        }

        $periodLabel = MonthlyDataPeriod::monthName($month) . ' ' . $year;

        return redirect()->route('bank-data.index', ['year' => $year, 'month' => $month, 'tab' => 'omzet'])
            ->with('success', "Data Omzet Bulanan {$periodLabel} berhasil dihapus dan di-reset!");
    }

    /**
     * Inline AJAX edit for a single Presensi cell in Bank Data Bulanan.
     */
    public function updatePresensiCell(Request $request)
    {
        $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'day' => ['required', 'integer', 'between:1,31'],
            'spg_id' => ['required', 'exists:spgs,id'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
        ]);

        $year = (int)$request->year;
        $month = (int)$request->month;
        $day = (int)$request->day;

        $period = MonthlyDataPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['has_presensi' => true, 'has_omzet' => false]
        );

        $outlet = $request->outlet_id ? Outlet::find($request->outlet_id) : null;
        $workDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

        BankPresensiDaily::updateOrCreate(
            [
                'work_date' => $workDate,
                'spg_id' => $request->spg_id,
            ],
            [
                'period_id' => $period->id,
                'day_number' => $day,
                'outlet_id' => $outlet ? $outlet->id : null,
                'raw_outlet_text' => $outlet ? $outlet->outlet_code : null,
            ]
        );

        if (!$period->has_presensi) {
            $period->update(['has_presensi' => true, 'presensi_uploaded_at' => now()]);
        }

        $totalDays = BankPresensiDaily::where('period_id', $period->id)
            ->where('spg_id', $request->spg_id)
            ->whereNotNull('outlet_id')
            ->count();

        return response()->json([
            'success' => true,
            'outlet_id' => $outlet ? $outlet->id : null,
            'outlet_code' => $outlet ? $outlet->outlet_code : null,
            'total_days' => $totalDays,
        ]);
    }

    /**
     * Inline AJAX edit for a single Omzet cell in Bank Data Bulanan.
     */
    public function updateOmzetCell(Request $request)
    {
        $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'day' => ['required', 'integer', 'between:1,31'],
            'outlet_id' => ['required', 'exists:outlets,id'],
            'omzet_amk' => ['required', 'numeric', 'min:0'],
            'omzet_amkopi' => ['required', 'numeric', 'min:0'],
        ]);

        $year = (int)$request->year;
        $month = (int)$request->month;
        $day = (int)$request->day;
        $amk = (float)$request->omzet_amk;
        $kopi = (float)$request->omzet_amkopi;
        $total = $amk + $kopi;

        $period = MonthlyDataPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['has_presensi' => false, 'has_omzet' => true]
        );

        $omzetDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

        BankOmzetDaily::updateOrCreate(
            [
                'omzet_date' => $omzetDate,
                'outlet_id' => $request->outlet_id,
            ],
            [
                'period_id' => $period->id,
                'day_number' => $day,
                'omzet_amk' => $amk,
                'omzet_amkopi' => $kopi,
                'total_omzet' => $total,
            ]
        );

        if (!$period->has_omzet) {
            $period->update(['has_omzet' => true, 'omzet_uploaded_at' => now()]);
        }

        $monthlySums = BankOmzetDaily::where('period_id', $period->id)
            ->where('outlet_id', $request->outlet_id)
            ->selectRaw('SUM(omzet_amk) as sum_amk, SUM(omzet_amkopi) as sum_kopi, SUM(total_omzet) as sum_total')
            ->first();

        return response()->json([
            'success' => true,
            'amk' => $amk,
            'kopi' => $kopi,
            'total' => $total,
            'outlet_monthly_amk' => (float)($monthlySums->sum_amk ?? 0),
            'outlet_monthly_kopi' => (float)($monthlySums->sum_kopi ?? 0),
            'outlet_monthly_total' => (float)($monthlySums->sum_total ?? 0),
        ]);
    }
}
