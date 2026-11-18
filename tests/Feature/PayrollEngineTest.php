<?php

namespace Tests\Feature;

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
use App\Models\User;
use App\Services\IncentiveCalculatorService;
use App\Services\SalaryCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        // Populate test fixture tasks in the isolated test database
        $bankService = app(\App\Services\BankDataService::class);
        $presensiSample = database_path('seeders/samples/presensirev.xlsx');
        $omzetSample = database_path('seeders/samples/omsetrev.xlsx');
        if (file_exists($presensiSample) && file_exists($omzetSample)) {
            foreach ([10, 11, 12] as $m) {
                $bankService->importPresensi($presensiSample, 2026, $m, 'presensirev.xlsx');
                $bankService->importOmzet($omzetSample, 2026, $m, 'omsetrev.xlsx');
            }
        }
        foreach ([
            ['type' => 'SALARY', 'month' => 12, 'year' => 2026, 'status' => 'FINISHED'],
            ['type' => 'INSENTIF', 'month' => 12, 'year' => 2026, 'status' => 'FINISHED'],
            ['type' => 'SALARY', 'month' => 1, 'year' => 2027, 'status' => 'DRAFT'],
            ['type' => 'INSENTIF', 'month' => 1, 'year' => 2027, 'status' => 'DRAFT'],
        ] as $tData) {
            $periodInfo = PayrollTask::calculateWorkingPeriod($tData['type'], $tData['month'], $tData['year']);
            $task = PayrollTask::create([
                'task_type' => $tData['type'],
                'title' => 'Payroll ' . ($tData['type'] === 'SALARY' ? 'Salary' : 'Insentif') . ' ' . $periodInfo['period_label'],
                'period_month' => $tData['month'],
                'period_year' => $tData['year'],
                'period_label' => $periodInfo['period_label'],
                'start_date' => $periodInfo['start_date'],
                'end_date' => $periodInfo['end_date'],
                'status' => 'DRAFT',
                'twin_outlet_mode' => 'full',
            ]);
            $bankService->syncTaskFromBankData($task, false);
            if ($tData['status'] === 'FINISHED') {
                $task->update(['status' => 'FINISHED', 'finished_at' => now()]);
            }
        }

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
        $response = $this->post('/login', [
            'login' => 'admin@payroll.local',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/tasks');
        $this->assertAuthenticatedAs($this->admin);

        $this->post('/logout');
        $this->assertGuest();

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
        // Insentif Januari 2027 (31 days: 1-31 Des 2026 from presensirev.xlsx & omsetrev.xlsx)
        $incTask = PayrollTask::where('task_type', 'INSENTIF')
            ->where('period_month', 1)
            ->where('period_year', 2027)
            ->first();

        // MEGA (> 5,000,000): AMK 110,204,000 + Kopi 13,215,000 = 123,419,000 -> Insentif = 62,500 + 2.5% * 118,419,000 = 3,022,975
        $megaRec = IncentiveRecord::where('payroll_task_id', $incTask->id)
            ->where('spg_id', $this->spgMega->id)
            ->first();

        $this->assertEquals(110204000.0, (float)$megaRec->total_omzet_amk);
        $this->assertEquals(13215000.0, (float)$megaRec->total_omzet_amkopi);
        $this->assertEquals(123419000.0, (float)$megaRec->total_omzet);
        $this->assertEquals(62500.0, (float)$megaRec->insentif_tier_1);
        $this->assertEquals(2960475.0, (float)$megaRec->insentif_tier_2);
        $this->assertEquals(3022975.0, (float)$megaRec->insentif_murni);

        // Flat <= 5,000,000 formula verification
        $spgShela = Spg::where('spg_name', 'SHELA')->first();
        $shelaRec = IncentiveRecord::where('payroll_task_id', $incTask->id)
            ->where('spg_id', $spgShela->id)
            ->first();
        $firstLog = $shelaRec->dailyLogs()->first();
        $firstLog->update(['outlet_id' => $this->outletDelta->id, 'outlet_code' => 'DELTA', 'omzet_amk' => 3500000, 'omzet_amkopi' => 0]);
        app(IncentiveCalculatorService::class)->calculateRecord($shelaRec->fresh(), null, true);
        $shelaRec->refresh();

        $this->assertEquals(3500000.0, (float)$shelaRec->total_omzet);
        $this->assertEquals(87500.0, (float)$shelaRec->insentif_murni);
    }

    public function test_salary_calculation_40_percent_threshold_and_policy_override(): void
    {
        $salaryTask = PayrollTask::where('task_type', 'SALARY')->where('period_month', 12)->first();
        $salaryTask->update(['status' => 'DRAFT']);

        // SPG TRI has 6 days present in 21 Okt - 20 Nov (6 / 26 = 23.08% < 40%)
        $spgTri = Spg::where('spg_name', 'TRI')->first();
        $triRec = SalaryRecord::where('payroll_task_id', $salaryTask->id)
            ->where('spg_id', $spgTri->id)
            ->first();

        $this->assertEquals(6, $triRec->total_hari_masuk);
        $this->assertLessThan(40.0, $triRec->attendance_percentage);
        $this->assertEquals((float)round(($triRec->base_salary_outlet / 26) * 6), (float)$triRec->base_salary_recommended);
        $this->assertEquals((float)$triRec->base_salary_recommended, (float)$triRec->base_salary_final);

        // Test Policy 100% full override toggle
        $response = $this->actingAs($this->admin)->post("/tasks/{$salaryTask->id}/policy/{$triRec->id}", [
            'policy_override' => true,
        ]);
        $response->assertStatus(200);

        $triRec->refresh();
        $this->assertTrue((bool)$triRec->policy_100_percent_override);
        $this->assertEquals((float)$triRec->base_salary_outlet, (float)$triRec->base_salary_final);
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

        $response = $this->actingAs($this->admin)->post("/tasks/{$task->id}/finish");
        $response->assertRedirect();
        $task->refresh();
        $this->assertEquals('FINISHED', $task->status);
        $this->assertNotNull($task->finished_at);

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

        $exportResponse = $this->actingAs($this->admin)->get('/export/annual?year=2026');
        $exportResponse->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $exportResponse->headers->get('content-type'));
    }

    public function test_master_data_crud(): void
    {
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

        $outlet = Outlet::where('outlet_code', 'SURABAYA-1')->first();
        $this->post('/master/aliases', [
            'outlet_id' => $outlet->id,
            'alias' => 'sb1',
        ]);
        $this->assertDatabaseHas('outlet_aliases', ['alias' => 'sb1']);

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
        $spgWahyu = Spg::where('spg_name', 'WAHYU')->first();
        $salaryRecord = SalaryRecord::where('payroll_task_id', $salaryTask->id)
            ->where('spg_id', $spgWahyu->id)
            ->first();

        // WAHYU rotates across multiple outlets (BG-L2, GC, ITC-UG, TP-1, WTC, R-4)
        $this->assertNotNull($salaryRecord);
        $this->assertIsArray($salaryRecord->outlets_summary);
        $this->assertGreaterThan(3, count($salaryRecord->outlets_summary));

        $dominantSummary = $salaryRecord->outlets_summary[0];
        $this->assertEquals($dominantSummary['id'], $salaryRecord->reference_outlet_id);
        $this->assertEquals('GC', $salaryRecord->referenceOutlet->outlet_code);

        $tp1Outlet = Outlet::where('outlet_code', 'TP-1')->first();
        $tp1Log = SalaryDailyLog::where('salary_record_id', $salaryRecord->id)
            ->where('chosen_outlet_id', $tp1Outlet->id)
            ->first();
        $this->assertNotNull($tp1Log);
        $expectedMeal = (float)$tp1Outlet->uang_makan_utama * (int)$tp1Log->multiplier;
        $this->assertEquals($expectedMeal, (float)$tp1Log->nominal_uang_makan);
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

        // Because Oktober 2026 is seeded in Bank Data Bulanan, MEGA's 31-day Oktober omzet is automatically populated!
        $megaRec = $newTask->incentiveRecords()->where('spg_id', $this->spgMega->id)->first();
        $this->assertEquals(123419000.0, (float)$megaRec->total_omzet);

        $viewResponse = $this->actingAs($this->admin)->get("/tasks/{$newTask->id}");
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Payroll Insentif');
        $viewResponse->assertSee('TOTAL OMZET');
    }

    public function test_spg_name_matching_distinguishes_pitri_and_fitri_and_dwi_and_dwie(): void
    {
        $allSpgs = Spg::all();
        $this->assertEquals(23, $allSpgs->count());

        $spgMap = [];
        foreach ($allSpgs as $s) {
            $spgMap[strtolower(trim($s->spg_name))] = $s;
        }

        $this->assertArrayHasKey('pitri', $spgMap);
        $this->assertArrayHasKey('fitri', $spgMap);
        $this->assertArrayHasKey('mega', $spgMap);
        $this->assertArrayHasKey('megha', $spgMap);
        $this->assertArrayHasKey('dwi', $spgMap);
        $this->assertArrayHasKey('dwie', $spgMap);

        $this->assertNotEquals($spgMap['pitri']->id, $spgMap['fitri']->id);
        $this->assertNotEquals($spgMap['mega']->id, $spgMap['megha']->id);
        $this->assertNotEquals($spgMap['dwi']->id, $spgMap['dwie']->id);
    }

    public function test_excel_matrix_parser_anomaly_detection(): void
    {
        $task = PayrollTask::where('task_type', 'SALARY')->first();
        $parser = app(\App\Services\ExcelMatrixParserService::class);

        $tempPath = storage_path('app/test_sample.xlsx');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');

        $sheet->setCellValue('A1', '');
        $sheet->setCellValue('B1', 'FITRI');
        $sheet->setCellValue('C1', 'MEGA');

        for ($day = 1; $day <= 31; $day++) {
            $row = $day + 1;
            $sheet->setCellValue('A' . $row, $day);
        }

        $sheet->setCellValue('B2', 'delta');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);

        $analysis = $parser->analyzeFile($tempPath, $task);
        $this->assertIsArray($analysis);
        $this->assertNotEmpty($analysis['presensi_rows']);
        $this->assertEquals('FITRI', $analysis['presensi_rows'][0]['spg_name']);
        $this->assertEmpty($analysis['anomalies']['unrecognized_spgs']);

        $uploadedFile = new UploadedFile(
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
    }

    public function test_bank_data_bulanan_uploads_and_full_inline_pivot_editing(): void
    {
        // 1. Access Bank Data Bulanan page
        $indexRes = $this->actingAs($this->admin)->get('/bank-data?year=2026&month=11');
        $indexRes->assertStatus(200);
        $indexRes->assertSee('Bank Data Bulanan (Presensi & Omzet)', false);

        // 2. Upload Presensi & Omzet for September 2026 via BankDataController
        $presensiSample = database_path('seeders/samples/presensirev.xlsx');
        $omzetSample = database_path('seeders/samples/omsetrev.xlsx');

        $tempPresensi = storage_path('app/temp_presensi_sep.xlsx');
        $tempOmzet = storage_path('app/temp_omzet_sep.xlsx');
        copy($presensiSample, $tempPresensi);
        copy($omzetSample, $tempOmzet);

        $pFile = new UploadedFile($tempPresensi, 'presensirev.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $oFile = new UploadedFile($tempOmzet, 'omsetrev.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $resP = $this->actingAs($this->admin)->post('/bank-data/upload-presensi', [
            'year' => 2026,
            'month' => 9,
            'presensi_file' => $pFile,
        ]);
        $resP->assertRedirect();

        $resO = $this->actingAs($this->admin)->post('/bank-data/upload-omzet', [
            'year' => 2026,
            'month' => 9,
            'omzet_file' => $oFile,
        ]);
        $resO->assertRedirect();

        @unlink($tempPresensi);
        @unlink($tempOmzet);

        $sepPeriod = MonthlyDataPeriod::where('year', 2026)->where('month', 9)->first();
        $this->assertNotNull($sepPeriod);
        $this->assertTrue($sepPeriod->has_presensi);
        $this->assertTrue($sepPeriod->has_omzet);

        // Verify R-1 (outlet without SPG) is stored in bank_omzet_daily
        $r1Day1 = BankOmzetDaily::where('period_id', $sepPeriod->id)
            ->where('outlet_id', $this->outletR1->id)
            ->where('day_number', 1)
            ->first();
        $this->assertNotNull($r1Day1);
        $this->assertEquals(1053000.0, (float)$r1Day1->omzet_amk);
        $this->assertEquals(298000.0, (float)$r1Day1->omzet_amkopi);

        // 3. Test Inline Cell Edit on Salary Task with Custom Daily Nominal Override & Update Bank Data
        $salaryTask = PayrollTask::where('task_type', 'SALARY')->where('period_month', 1)->where('period_year', 2027)->first();
        $megaSalaryRec = $salaryTask->salaryRecords()->where('spg_id', $this->spgMega->id)->first();

        $cellEditRes = $this->actingAs($this->admin)->postJson("/tasks/{$salaryTask->id}/cell", [
            'record_id' => $megaSalaryRec->id,
            'date' => '2026-11-21',
            'raw_value' => 'PTC',
            'custom_nominal_override' => 75000,
            'update_bank_data' => true,
        ]);
        $cellEditRes->assertStatus(200)->assertJson(['success' => true]);

        $updatedLog = SalaryDailyLog::where('salary_record_id', $megaSalaryRec->id)
            ->whereDate('date', '2026-11-21')
            ->first();
        $this->assertEquals('PTC', $updatedLog->outlet_code);
        $this->assertEquals(75000.0, (float)$updatedLog->custom_nominal_override);
        $this->assertEquals(75000.0, (float)$updatedLog->nominal_uang_makan);
        $this->assertTrue($updatedLog->is_manual_override);

        // Verify BankPresensiDaily was also updated for 2026-11-21
        $bankCell = BankPresensiDaily::where('work_date', '2026-11-21')
            ->where('spg_id', $this->spgMega->id)
            ->first();
        $this->assertEquals('PTC', $bankCell->raw_outlet_text);
    }
}
