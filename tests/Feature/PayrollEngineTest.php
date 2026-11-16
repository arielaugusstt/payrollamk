<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Outlet $outletDelta;
    protected Outlet $outletR1;
    protected Outlet $outletItc;
    protected Spg $spgMega;
    protected Spg $spgFitri;
    protected Spg $spgDewi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('email', 'admin@payroll.local')->first();
        $this->outletDelta = Outlet::where('outlet_code', 'DELTA')->first();
        $this->outletR1 = Outlet::where('outlet_code', 'R-1')->first();
        $this->outletItc = Outlet::where('outlet_code', 'ITC')->first();

        $this->spgMega = Spg::where('spg_name', 'MEGA')->first();
        $this->spgFitri = Spg::where('spg_name', 'FITRI')->first();
        $this->spgDewi = Spg::where('spg_name', 'DWIE')->first();
    }

    public function test_login_page_renders_with_cat_elements(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Kucing Oren');
        $response->assertSee('Payroll');
    }

    public function test_login_successful_with_email_and_username(): void
    {
        // With email
        $response = $this->post('/login', [
            'login' => 'admin@payroll.local',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($this->admin);

        // Logout
        $this->post('/logout');
        $this->assertGuest();

        // With username
        $response = $this->post('/login', [
            'login' => 'admin',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_automatic_working_period_mapping(): void
    {
        // Salary Desember 2026: 21 Okt 2026 – 20 Nov 2026
        $salDes26 = PayrollTask::calculateWorkingPeriod('SALARY', 12, 2026);
        $this->assertEquals('2026-10-21', $salDes26['start_date']);
        $this->assertEquals('2026-11-20', $salDes26['end_date']);

        // Salary Januari 2027: 21 Nov 2026 – 20 Des 2026
        $salJan27 = PayrollTask::calculateWorkingPeriod('SALARY', 1, 2027);
        $this->assertEquals('2026-11-21', $salJan27['start_date']);
        $this->assertEquals('2026-12-20', $salJan27['end_date']);

        // Salary Februari 2027: 21 Des 2026 – 20 Jan 2027
        $salFeb27 = PayrollTask::calculateWorkingPeriod('SALARY', 2, 2027);
        $this->assertEquals('2026-12-21', $salFeb27['start_date']);
        $this->assertEquals('2027-01-20', $salFeb27['end_date']);

        // Insentif Desember 2026: 1 Nov 2026 – 30 Nov 2026
        $incDes26 = PayrollTask::calculateWorkingPeriod('INSENTIF', 12, 2026);
        $this->assertEquals('2026-11-01', $incDes26['start_date']);
        $this->assertEquals('2026-11-30', $incDes26['end_date']);

        // Insentif Januari 2027: 1 Des 2026 – 31 Des 2026
        $incJan27 = PayrollTask::calculateWorkingPeriod('INSENTIF', 1, 2027);
        $this->assertEquals('2026-12-01', $incJan27['start_date']);
        $this->assertEquals('2026-12-31', $incJan27['end_date']);
    }

    public function test_incentive_calculation_tiered_and_flat_formulas(): void
    {
        $incentiveCalc = app(IncentiveCalculatorService::class);
        $settings = IncentiveSetting::current();

        // Test Case 1: Mega with Rp 22.500.000 -> 62.500 + 2.5% * 17.500.000 (437.500) = Rp 500.000
        $megaTask = PayrollTask::where('task_type', 'INSENTIF')->where('period_month', 12)->first();
        $megaRec = IncentiveRecord::where('payroll_task_id', $megaTask->id)
            ->where('spg_id', $this->spgMega->id)
            ->first();

        $this->assertEquals(22500000.0, (float)$megaRec->total_omzet);
        $this->assertEquals(62500.0, (float)$megaRec->insentif_tier_1);
        $this->assertEquals(437500.0, (float)$megaRec->insentif_tier_2);
        $this->assertEquals(500000.0, (float)$megaRec->insentif_murni);

        // Test Case 2: Fitri with Rp 3.500.000 -> 2.5% * 3.500.000 = Rp 87.500
        $fitriRec = IncentiveRecord::where('payroll_task_id', $megaTask->id)
            ->where('spg_id', $this->spgFitri->id)
            ->first();

        $this->assertEquals(3500000.0, (float)$fitriRec->total_omzet);
        $this->assertEquals(87500.0, (float)$fitriRec->insentif_murni);
    }

    public function test_salary_calculation_40_percent_threshold_and_policy_override(): void
    {
        $salaryCalc = app(SalaryCalculatorService::class);
        $salaryTask = PayrollTask::where('task_type', 'SALARY')->where('period_month', 12)->first();
        // Ensure task is DRAFT to allow toggle
        $salaryTask->update(['status' => 'DRAFT']);

        // SPG PUJI has 8 days present (8 / 26 = 30.77% < 40%)
        $spgPuji = Spg::where('spg_name', 'PUJI')->first();
        $pujiRec = SalaryRecord::where('payroll_task_id', $salaryTask->id)
            ->where('spg_id', $spgPuji->id)
            ->first();

        $this->assertEquals(8, $pujiRec->total_hari_masuk);
        $this->assertLessThan(40.0, $pujiRec->attendance_percentage);
        $this->assertEquals((float)round(($pujiRec->base_salary_outlet / 26) * 8), (float)$pujiRec->base_salary_recommended);
        $this->assertEquals((float)$pujiRec->base_salary_recommended, (float)$pujiRec->base_salary_final);

        // Test Policy 100% full override toggle
        $response = $this->actingAs($this->admin)->post("/tasks/{$salaryTask->id}/policy/{$pujiRec->id}", [
            'policy_override' => true,
        ]);
        $response->assertStatus(200);

        $pujiRec->refresh();
        $this->assertTrue((bool)$pujiRec->policy_100_percent_override);
        $this->assertEquals((float)$pujiRec->base_salary_outlet, (float)$pujiRec->base_salary_final);
    }

    public function test_multi_code_highest_constant_rule(): void
    {
        $salaryCalc = app(SalaryCalculatorService::class);
        $outletsMap = Outlet::all()->keyBy(fn($o) => strtoupper($o->outlet_code));
        $allAliases = OutletAlias::with('outlet')->get();
        $aliasMap = [];
        foreach ($allAliases as $al) {
            $aliasMap[strtolower(trim($al->alias))] = $al->outlet;
        }

        // DELTA has 36.000, ITC has 45.713
        // When cell is "DELTA ITC", chosen outlet should be ITC (higher constant: 45.713 vs 36.000)
        $resolved = $salaryCalc->resolveCellPresenceAndOutlet('DELTA ITC', $this->outletDelta, $outletsMap, $aliasMap);

        $this->assertTrue($resolved['is_present']);
        $this->assertEquals($this->outletItc->id, $resolved['chosen_outlet_id']);
    }

    public function test_vertical_slip_card_view(): void
    {
        $salaryTask = PayrollTask::where('task_type', 'SALARY')->first();
        $record = SalaryRecord::where('payroll_task_id', $salaryTask->id)->first();

        $response = $this->actingAs($this->admin)->get("/slips/{$record->id}");
        $response->assertStatus(200);
        $response->assertSee('SLIP RINCIAN GAJI SPG');
        $response->assertSee($record->spg->spg_name);
        $response->assertSee('GRAND TOTAL:');
    }

    public function test_task_finish_and_reopen(): void
    {
        $task = PayrollTask::where('status', 'DRAFT')->first();

        // Finish task
        $response = $this->actingAs($this->admin)->post("/tasks/{$task->id}/finish");
        $response->assertRedirect();
        $task->refresh();
        $this->assertEquals('FINISHED', $task->status);
        $this->assertNotNull($task->finished_at);

        // Reopen task
        $response = $this->actingAs($this->admin)->post("/tasks/{$task->id}/reopen");
        $response->assertRedirect();
        $task->refresh();
        $this->assertEquals('DRAFT', $task->status);
    }

    public function test_executive_dashboard_and_export(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard?year=2026&component=combined');
        $response->assertStatus(200);
        $response->assertSee('Grand Total Payroll Outflow');

        // Test export annual matrix
        $exportResponse = $this->actingAs($this->admin)->get('/export/annual?year=2026');
        $exportResponse->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $exportResponse->headers->get('content-type'));
    }

    public function test_master_data_crud(): void
    {
        // Add Outlet
        $response = $this->actingAs($this->admin)->post('/master/outlets', [
            'outlet_code' => 'SURABAYA-1',
            'outlet_name' => 'Surabaya Mall',
            'gaji_pokok' => 400000,
            'uang_makan_utama' => 35000,
            'uang_makan_tambahan' => 0,
            'uang_pulsa' => 15000,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('outlets', ['outlet_code' => 'SURABAYA-1']);

        // Add Alias
        $outlet = Outlet::where('outlet_code', 'SURABAYA-1')->first();
        $this->post('/master/aliases', [
            'outlet_id' => $outlet->id,
            'alias' => 'sb1',
        ]);
        $this->assertDatabaseHas('outlet_aliases', ['alias' => 'sb1']);

        // Add SPG (with optional default outlet)
        $this->post('/master/spgs', [
            'spg_name' => 'Clara',
            'default_outlet_id' => $outlet->id,
            'aliases' => 'clara, clara sby',
        ]);
        $this->assertDatabaseHas('spgs', ['spg_name' => 'Clara', 'default_outlet_id' => $outlet->id]);
    }

    public function test_dynamic_daily_outlet_switching_and_dominant_outlet_resolution(): void
    {
        $salaryTask = PayrollTask::where('task_type', 'SALARY')->where('period_month', 12)->first();
        $salaryRecord = SalaryRecord::where('payroll_task_id', $salaryTask->id)
            ->where('spg_id', $this->spgFitri->id)
            ->first();

        // Fitri in seeder worked 18 days at DELTA, 5 days at TP-2, 1 day at PA-1
        $this->assertNotNull($salaryRecord);
        $this->assertIsArray($salaryRecord->outlets_summary);

        $summaryMap = collect($salaryRecord->outlets_summary)->pluck('days', 'code')->all();
        $this->assertEquals(18, $summaryMap['DELTA'] ?? 0);
        $this->assertEquals(5, $summaryMap['TP-2'] ?? 0);
        $this->assertEquals(1, $summaryMap['PA-1'] ?? 0);

        // Dominant outlet should resolve to DELTA
        $this->assertEquals($this->outletDelta->id, $salaryRecord->reference_outlet_id);
        $this->assertEquals('DELTA', $salaryRecord->referenceOutlet->outlet_code);
        $this->assertEquals((float)$this->outletDelta->gaji_pokok, (float)$salaryRecord->base_salary_outlet);

        // Daily meal allowances should be calculated strictly per date's outlet
        $tp2Outlet = Outlet::where('outlet_code', 'TP-2')->first();
        $tp2Log = SalaryDailyLog::where('salary_record_id', $salaryRecord->id)
            ->where('chosen_outlet_id', $tp2Outlet->id)
            ->first();
        $this->assertNotNull($tp2Log);
        $expectedMeal = (float)$tp2Outlet->uang_makan_utama * (int)$tp2Log->multiplier;
        $this->assertEquals($expectedMeal, (float)$tp2Log->nominal_uang_makan);
    }

    public function test_manual_reference_outlet_override(): void
    {
        $salaryTask = PayrollTask::where('task_type', 'SALARY')->where('period_month', 12)->first();
        $salaryTask->update(['status' => 'DRAFT']);

        $salaryRecord = SalaryRecord::where('payroll_task_id', $salaryTask->id)
            ->where('spg_id', $this->spgFitri->id)
            ->first();

        $pa1Outlet = Outlet::where('outlet_code', 'PA-1')->first();
        $this->assertNotNull($pa1Outlet);

        // Override reference outlet to PA-1
        $response = $this->actingAs($this->admin)->post("/tasks/{$salaryTask->id}/reference-outlet/{$salaryRecord->id}", [
            'reference_outlet_id' => $pa1Outlet->id,
        ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $salaryRecord->refresh();
        $this->assertEquals($pa1Outlet->id, $salaryRecord->reference_outlet_id);
        $this->assertEquals((float)$pa1Outlet->gaji_pokok, (float)$salaryRecord->base_salary_outlet);
        $this->assertEquals((float)$pa1Outlet->gaji_pokok, (float)$salaryRecord->base_salary_final);
    }

    public function test_create_new_incentive_task_renders_matrix_without_errors(): void
    {
        // Create brand new INSENTIF task for November 2026
        $response = $this->actingAs($this->admin)->post('/tasks', [
            'task_type' => 'INSENTIF',
            'period_month' => 11,
            'period_year' => 2026,
            'notes' => 'Testing new incentive task rendering',
        ]);
        $response->assertRedirect();

        $newTask = PayrollTask::where('task_type', 'INSENTIF')
            ->where('period_month', 11)
            ->where('period_year', 2026)
            ->first();

        $this->assertNotNull($newTask);

        // Access the show view of the newly created task (which previously threw Attempt to read property "outlet_code" on null)
        $viewResponse = $this->actingAs($this->admin)->get("/tasks/{$newTask->id}");
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Payroll Insentif');
        $viewResponse->assertSee('TOTAL OMZET');
    }

    public function test_spg_name_matching_distinguishes_pitri_and_fitri_and_dwi_and_dwie(): void
    {
        $allSpgs = Spg::all();
        $this->assertGreaterThanOrEqual(20, $allSpgs->count());

        $spgMap = [];
        foreach ($allSpgs as $s) {
            $spgMap[strtolower(trim($s->spg_name))] = $s;
        }

        // Test case-insensitive exact matching
        $this->assertArrayHasKey('pitri', $spgMap);
        $this->assertArrayHasKey('fitri', $spgMap);
        $this->assertArrayHasKey('dwi', $spgMap);
        $this->assertArrayHasKey('dwie', $spgMap);

        $spgPitri = $spgMap['pitri'];
        $spgFitri = $spgMap['fitri'];
        $spgDwi = $spgMap['dwi'];
        $spgDwie = $spgMap['dwie'];

        $this->assertNotEquals($spgPitri->id, $spgFitri->id);
        $this->assertEquals('PITRI', $spgPitri->spg_name);
        $this->assertEquals('FITRI', $spgFitri->spg_name);

        $this->assertNotEquals($spgDwi->id, $spgDwie->id);
        $this->assertEquals('DWI', $spgDwi->spg_name);
        $this->assertEquals('DWIE', $spgDwie->spg_name);
    }

    public function test_excel_matrix_parser_anomaly_detection(): void
    {
        $task = PayrollTask::where('task_type', 'SALARY')->first();
        $parser = app(\App\Services\ExcelMatrixParserService::class);

        $tempPath = storage_path('app/test_sample.xlsx');
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');
        
        // Structure following official presensi.xlsx:
        // Row 1: Col B onwards = SPG Names
        $sheet->setCellValue('A1', '');
        $sheet->setCellValue('B1', 'FITRI');
        $sheet->setCellValue('C1', 'MEGA');
        
        // Col A: Dates 1 to 31 running vertically
        for ($day = 1; $day <= 31; $day++) {
            $row = $day + 1;
            $sheet->setCellValue('A' . $row, $day);
        }

        // FITRI worked at delta on Day 1
        $sheet->setCellValue('B2', 'delta');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);

        $analysis = $parser->analyzeFile($tempPath, $task);
        $this->assertIsArray($analysis);
        $this->assertNotEmpty($analysis['presensi_rows']);
        $this->assertEquals('FITRI', $analysis['presensi_rows'][0]['spg_name']);
        $this->assertEmpty($analysis['anomalies']['unrecognized_spgs']);

        // Test Automatic Upload without manual mapping screen
        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempPath,
            'test_sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $uploadResponse = $this->actingAs($this->admin)->post("/tasks/{$task->id}/upload", [
            'excel_file' => $uploadedFile,
        ]);
        $uploadResponse->assertRedirect("/tasks/{$task->id}");
        $uploadResponse->assertSessionHas('success');

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        // Test Automatic Upload for INSENTIF task with Presensi & Omzet sheet
        $incTask = PayrollTask::where('task_type', 'INSENTIF')->first();
        $tempIncPath = storage_path('app/test_sample_omzet.xlsx');

        $incSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $presensiSheet = $incSpreadsheet->getActiveSheet();
        $presensiSheet->setTitle('Sheet1');
        $presensiSheet->setCellValue('B1', 'MEGA');
        for ($day = 1; $day <= 31; $day++) {
            $presensiSheet->setCellValue('A' . ($day + 1), $day);
        }
        $presensiSheet->setCellValue('B2', 'R-1');

        $omzetSheet = $incSpreadsheet->createSheet();
        $omzetSheet->setTitle('omset');

        // Omzet Sheet structure: Row 1 = Dates (Col B=1, Col C=2, ...), Col A = Outlets
        $omzetSheet->setCellValue('A1', '');
        for ($day = 1; $day <= 31; $day++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($day + 1);
            $omzetSheet->setCellValue($colLetter . '1', $day);
        }
        $omzetSheet->setCellValue('A2', 'R-1');
        $omzetSheet->setCellValue('B2', 500000);

        $incWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($incSpreadsheet);
        $incWriter->save($tempIncPath);

        $incUploadedFile = new \Illuminate\Http\UploadedFile(
            $tempIncPath,
            'test_sample_omzet.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $incUploadResponse = $this->actingAs($this->admin)->post("/tasks/{$incTask->id}/upload", [
            'excel_file' => $incUploadedFile,
        ]);
        $incUploadResponse->assertRedirect("/tasks/{$incTask->id}");
        $incUploadResponse->assertSessionHas('success');

        if (file_exists($tempIncPath)) {
            unlink($tempIncPath);
        }
    }

    public function test_baku_format_upload_automatic_import_salary_and_incentive(): void
    {
        $bakuPath = '/Users/arielaugusta/.gemini/antigravity/brain/6889cbbe-7e2e-448d-a3c8-8fd8a7bb35c6/.user_uploaded/media_1794807459875_fbf45155.xlsx';
        if (!file_exists($bakuPath)) {
            $this->markTestSkipped('Baku file not available at specified path');
        }

        // Test 1: Upload to Salary Task
        $salaryTask = PayrollTask::where('task_type', 'SALARY')->first();
        $uploadedSalary = new \Illuminate\Http\UploadedFile(
            $bakuPath,
            'presensi_baku.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $salaryRes = $this->actingAs($this->admin)->post("/tasks/{$salaryTask->id}/upload", [
            'excel_file' => $uploadedSalary,
        ]);
        $salaryRes->assertRedirect("/tasks/{$salaryTask->id}");
        $salaryRes->assertSessionHas('success');

        $zildaRec = SalaryRecord::where('payroll_task_id', $salaryTask->id)
            ->whereHas('spg', fn($q) => $q->where('spg_name', 'ZILDA'))
            ->first();
        $this->assertNotNull($zildaRec);
        $this->assertEquals(27, $zildaRec->total_hari_masuk);
        $this->assertGreaterThan(0, $zildaRec->total_salary);

        // Test 2: Upload to Incentive Task
        $incTask = PayrollTask::where('task_type', 'INSENTIF')->first();
        if (!$incTask) {
            $incTask = PayrollTask::create([
                'title' => 'Payroll Insentif Baku Test',
                'task_type' => 'INSENTIF',
                'period_month' => 10,
                'period_year' => 2026,
                'period_label' => 'Oktober 2026',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'status' => 'DRAFT',
            ]);
        }

        $uploadedInc = new \Illuminate\Http\UploadedFile(
            $bakuPath,
            'omzet_baku.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $incRes = $this->actingAs($this->admin)->post("/tasks/{$incTask->id}/upload", [
            'excel_file' => $uploadedInc,
        ]);
        $incRes->assertRedirect("/tasks/{$incTask->id}");
        $incRes->assertSessionHas('success');

        $megaInc = IncentiveRecord::where('payroll_task_id', $incTask->id)
            ->whereHas('spg', fn($q) => $q->where('spg_name', 'MEGA'))
            ->first();
        $this->assertNotNull($megaInc);
        $this->assertGreaterThan(0, $megaInc->total_omzet);
        $this->assertGreaterThan(0, $megaInc->total_insentif);
    }
}
