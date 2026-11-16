<?php

namespace App\Http\Controllers;

use App\Models\PayrollTask;
use App\Models\Spg;
use App\Services\MatrixExcelExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    protected MatrixExcelExportService $exporter;

    public function __construct(MatrixExcelExportService $exporter)
    {
        $this->exporter = $exporter;
    }

    public function exportSalary(PayrollTask $task)
    {
        return $this->exporter->exportSalaryMatrix($task);
    }

    public function exportIncentive(PayrollTask $task)
    {
        return $this->exporter->exportIncentiveMatrix($task);
    }

    public function exportAnnual(Request $request)
    {
        $year = (int)$request->query('year', 2026);
        $componentView = $request->query('component', 'combined');

        // Reuse Dashboard logic to get exact matrix rows & totals
        $dashboardController = app(DashboardController::class);
        $view = $dashboardController->index($request);
        $data = $view->getData();

        return $this->exporter->exportAnnualRecap($year, $data['spgMatrixRows'], $data['monthlyTotals']);
    }

    public function downloadTemplate(PayrollTask $task)
    {
        return $this->exporter->generateSampleTemplate($task);
    }
}
