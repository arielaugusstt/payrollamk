<?php

namespace App\Services;

use App\Models\PayrollTask;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatrixExcelExportService
{
    /**
     * Export Salary Task Horizontal Matrix with dynamic daily outlets.
     */
    public function exportSalaryMatrix(PayrollTask $task): StreamedResponse
    {
        $task->load(['salaryRecords.spg.defaultOutlet', 'salaryRecords.referenceOutlet', 'salaryRecords.dailyLogs.chosenOutlet', 'dateMultipliers']);
        $dates = $task->getDatesList();
        $multipliersMap = $task->dateMultipliers->keyBy(fn($m) => $m->date->format('Y-m-d'));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Salary Matrix');

        // Title Block
        $sheet->setCellValue('A1', 'REKAPITULASI GAJI SPG - ' . strtoupper($task->period_label));
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Periode Kerja: ' . $task->start_date->format('d/m/Y') . ' s/d ' . $task->end_date->format('d/m/Y') . ' | Status: ' . $task->status . ' (Dynamic Daily Outlets)');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        // Header Row 4: Static Columns
        $sheet->setCellValue('A4', 'NO');
        $sheet->setCellValue('B4', 'NAMA SPG');
        $sheet->setCellValue('C4', "OUTLET DIKUNJUNGI (REF POKOK)");

        $colIdx = 4; // Column D
        foreach ($dates as $date) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $ymd = $date->format('Y-m-d');
            $mult = $multipliersMap->get($ymd);
            $multVal = $mult ? $mult->multiplier : 1;
            
            $headerText = $date->format('d');
            if ($multVal > 1) {
                $headerText .= "\n(" . $multVal . "x)";
            }

            $sheet->setCellValue($colLetter . '4', $headerText);
            $colIdx++;
        }

        // Summary Header Columns
        $summaryHeaders = [
            'HARI MASUK',
            '% ABSEN',
            'UANG MAKAN UTAMA',
            'UANG MAKAN TAMBAHAN',
            'GAJI POKOK REK.',
            'KEBIJAKAN 100%',
            'GAJI POKOK FINAL',
            'UANG PULSA',
            'UANG TAMBAHAN',
            'TOTAL SALARY (RP)',
        ];

        foreach ($summaryHeaders as $sHeader) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue($colLetter . '4', $sHeader);
            $colIdx++;
        }

        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx - 1);

        // Header Styling
        $sheet->getStyle('A4:' . $lastColLetter . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B']
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(32);

        // Populate Data Rows
        $rowIdx = 5;
        $no = 1;
        foreach ($task->salaryRecords as $rec) {
            $sheet->setCellValue('A' . $rowIdx, $no++);
            $sheet->setCellValue('B' . $rowIdx, $rec->spg->spg_name);

            // Outlets Visited summary string
            $visitedStr = '';
            if ($rec->outlets_summary && is_array($rec->outlets_summary)) {
                $p = [];
                foreach ($rec->outlets_summary as $item) {
                    $p[] = "{$item['code']} ({$item['days']}d)";
                }
                $visitedStr = implode(', ', $p);
            }
            if ($rec->referenceOutlet) {
                $visitedStr .= ($visitedStr ? ' | ' : '') . 'Ref: ' . $rec->referenceOutlet->outlet_code;
            }
            $sheet->setCellValue('C' . $rowIdx, $visitedStr ?: '-');

            $cIdx = 4;
            $logsMap = $rec->dailyLogs->keyBy(fn($l) => $l->date->format('Y-m-d'));

            foreach ($dates as $date) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                $ymd = $date->format('Y-m-d');
                $log = $logsMap->get($ymd);
                $val = '-';
                if ($log && $log->is_present) {
                    $code = $log->outlet_code ?: ($log->chosenOutlet ? $log->chosenOutlet->outlet_code : ($log->raw_cell_value ?: 'OK'));
                    $dailyTotal = (float)$log->daily_uang_makan_utama + (float)$log->daily_uang_makan_tambahan;
                    $val = $code . ($dailyTotal > 0 ? "\nRp " . number_format($dailyTotal, 0, ',', '.') : '');
                }
                $sheet->setCellValue($cLetter . $rowIdx, $val);
                $sheet->getStyle($cLetter . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $cIdx++;
            }

            // Summary values
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->total_hari_masuk);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->attendance_percentage . '%');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->uang_makan_utama);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->uang_makan_tambahan);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->base_salary_recommended);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->policy_100_percent_override ? 'YA' : 'TIDAK');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->base_salary_final);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->uang_pulsa);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->uang_tambahan);

            $totalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++);
            $sheet->setCellValue($totalColLetter . $rowIdx, $rec->total_salary);
            $sheet->getStyle($totalColLetter . $rowIdx)->getFont()->setBold(true)->getColor()->setRGB('DC2626');

            $sheet->getStyle('A' . $rowIdx . ':' . $lastColLetter . $rowIdx)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);

            $rowIdx++;
        }

        $sheet->freezePane('D5');
        $sheet->getStyle('G5:' . $lastColLetter . ($rowIdx - 1))->getNumberFormat()->setFormatCode('#,##0');

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(26);

        $this->addVerificationSheet($spreadsheet, $task, $dates);

        $filename = 'Salary_' . str_replace(' ', '_', $task->period_label) . '.xlsx';
        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    /**
     * Export Incentive Task Horizontal Matrix.
     */
    public function exportIncentiveMatrix(PayrollTask $task): StreamedResponse
    {
        $task->load(['incentiveRecords.spg.defaultOutlet', 'incentiveRecords.referenceOutlet', 'incentiveRecords.dailyLogs.outlet']);
        $dates = $task->getDatesList();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Incentive Matrix');

        // Title Block
        $sheet->setCellValue('A1', 'REKAPITULASI INSENTIF OMZET SPG - ' . strtoupper($task->period_label));
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Periode Omzet: ' . $task->start_date->format('d/m/Y') . ' s/d ' . $task->end_date->format('d/m/Y') . ' | Status: ' . $task->status);
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        // Header Row 4: Static Columns
        $sheet->setCellValue('A4', 'NO');
        $sheet->setCellValue('B4', 'NAMA SPG');
        $sheet->setCellValue('C4', 'OUTLETS VISITED');

        $colIdx = 4;
        foreach ($dates as $date) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue($colLetter . '4', $date->format('d'));
            $colIdx++;
        }

        // Summary Header Columns
        $summaryHeaders = [
            'OMZET AMK',
            'OMZET AMKOPI',
            'TOTAL OMZET',
            'INSENTIF TIER 1 (1.25%)',
            'INSENTIF TIER 2 (2.5%)',
            'INSENTIF MURNI',
            'UANG TAMBAHAN',
            'TOTAL INSENTIF (RP)',
        ];

        foreach ($summaryHeaders as $sHeader) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue($colLetter . '4', $sHeader);
            $colIdx++;
        }

        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx - 1);

        // Header Styling
        $sheet->getStyle('A4:' . $lastColLetter . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '047857']
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6EE7B7']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(28);

        $rowIdx = 5;
        $no = 1;
        foreach ($task->incentiveRecords as $rec) {
            $sheet->setCellValue('A' . $rowIdx, $no++);
            $sheet->setCellValue('B' . $rowIdx, $rec->spg->spg_name);

            $visitedStr = '';
            if ($rec->outlets_summary && is_array($rec->outlets_summary)) {
                $p = [];
                foreach ($rec->outlets_summary as $item) {
                    $p[] = $item['code'];
                }
                $visitedStr = implode(', ', array_unique($p));
            }
            $sheet->setCellValue('C' . $rowIdx, $visitedStr ?: '-');

            $cIdx = 4;
            $logsMap = $rec->dailyLogs->keyBy(fn($l) => $l->date->format('Y-m-d'));

            foreach ($dates as $date) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                $ymd = $date->format('Y-m-d');
                $log = $logsMap->get($ymd);
                $val = '-';
                if ($log && ($log->outlet_code || $log->total_daily_omzet > 0)) {
                    $code = $log->outlet_code ?: '-';
                    $omz = (float)$log->total_daily_omzet;
                    $val = $code . ($omz > 0 ? "\nRp " . number_format($omz, 0, ',', '.') : '');
                }
                $sheet->setCellValue($cLetter . $rowIdx, $val);
                $sheet->getStyle($cLetter . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $cIdx++;
            }

            // Summary values
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->total_omzet_amk);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->total_omzet_amkopi);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->total_omzet);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->insentif_tier_1);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->insentif_tier_2);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->insentif_murni);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++) . $rowIdx, $rec->uang_tambahan);

            $totalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx++);
            $sheet->setCellValue($totalColLetter . $rowIdx, $rec->total_insentif);
            $sheet->getStyle($totalColLetter . $rowIdx)->getFont()->setBold(true)->getColor()->setRGB('047857');

            $sheet->getStyle('A' . $rowIdx . ':' . $lastColLetter . $rowIdx)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);

            $rowIdx++;
        }

        $sheet->freezePane('D5');
        $sheet->getStyle('D5:' . $lastColLetter . ($rowIdx - 1))->getNumberFormat()->setFormatCode('#,##0');

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(22);

        $this->addVerificationSheet($spreadsheet, $task, $dates);

        $filename = 'Insentif_' . str_replace(' ', '_', $task->period_label) . '.xlsx';
        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    /**
     * Add Sheet 2: Verifikasi Outlet Per Tanggal.
     */
    protected function addVerificationSheet(Spreadsheet $spreadsheet, PayrollTask $task, array $dates): void
    {
        $allOutlets = \App\Models\Outlet::where('is_active', true)->orderBy('outlet_code')->get();
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Verifikasi Outlet');

        // Title Block
        $sheet->setCellValue('A1', 'VERIFIKASI OUTLET PER TANGGAL - ' . strtoupper($task->period_label));
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->setCellValue('A2', 'Rincian Penjualan Outlet & SPG Bertugas Per Tanggal (1 s/d 31)');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        // Header Row 4
        $sheet->setCellValue('A4', 'NO');
        $sheet->setCellValue('B4', 'KODE OUTLET');
        $sheet->setCellValue('C4', 'NAMA OUTLET');

        $colIdx = 4;
        foreach ($dates as $date) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue($colLetter . '4', $date->format('d'));
            $colIdx++;
        }
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx - 1);

        $sheet->getStyle('A4:' . $lastColLetter . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A']
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '64748B']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(26);

        // Load logs
        $rowIdx = 5;
        $no = 1;

        if ($task->isSalary()) {
            $records = $task->salaryRecords()->with(['spg', 'dailyLogs.chosenOutlet'])->get();
            foreach ($allOutlets as $outlet) {
                $outCode = $outlet->outlet_code;
                $sheet->setCellValue('A' . $rowIdx, $no++);
                $sheet->setCellValue('B' . $rowIdx, $outCode);
                $sheet->setCellValue('C' . $rowIdx, $outlet->outlet_name);

                $cIdx = 4;
                foreach ($dates as $date) {
                    $ymd = $date->format('Y-m-d');
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                    $spgs = [];
                    foreach ($records as $r) {
                        $log = $r->dailyLogs->first(fn($l) => $l->date->format('Y-m-d') === $ymd);
                        if ($log && $log->is_present && ($log->outlet_code === $outCode || $log->chosen_outlet_id === $outlet->id)) {
                            $spgs[] = $r->spg->spg_name;
                        }
                    }
                    $cellText = empty($spgs) ? '-' : implode(', ', $spgs);
                    $sheet->setCellValue($colLetter . $rowIdx, $cellText);
                    $sheet->getStyle($colLetter . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                    $cIdx++;
                }

                $sheet->getStyle('A' . $rowIdx . ':' . $lastColLetter . $rowIdx)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                ]);
                $rowIdx++;
            }
        } else {
            $records = $task->incentiveRecords()->with(['spg', 'dailyLogs'])->get();
            foreach ($allOutlets as $outlet) {
                $outCode = $outlet->outlet_code;
                $sheet->setCellValue('A' . $rowIdx, $no++);
                $sheet->setCellValue('B' . $rowIdx, $outCode);
                $sheet->setCellValue('C' . $rowIdx, $outlet->outlet_name);

                $cIdx = 4;
                foreach ($dates as $date) {
                    $ymd = $date->format('Y-m-d');
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                    $spgs = [];
                    $totalOmzet = 0.0;
                    foreach ($records as $r) {
                        $log = $r->dailyLogs->first(fn($l) => $l->date->format('Y-m-d') === $ymd);
                        if ($log && ($log->outlet_code === $outCode || $log->outlet_id === $outlet->id)) {
                            $spgs[] = $r->spg->spg_name;
                            $totalOmzet += (float)$log->omzet_amk + (float)$log->omzet_amkopi;
                        }
                    }

                    if (empty($spgs) && $totalOmzet == 0) {
                        $cellText = '-';
                    } else {
                        $cellText = (empty($spgs) ? '[TANPA SPG]' : implode(', ', $spgs));
                        if ($totalOmzet > 0) {
                            $cellText .= "\nRp " . number_format($totalOmzet, 0, ',', '.');
                        }
                    }

                    $sheet->setCellValue($colLetter . $rowIdx, $cellText);
                    $sheet->getStyle($colLetter . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                    $cIdx++;
                }

                $sheet->getStyle('A' . $rowIdx . ':' . $lastColLetter . $rowIdx)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                ]);
                $rowIdx++;
            }
        }

        $sheet->freezePane('D5');
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(26);
    }

    /**
     * Export Annual Recap Matrix.
     */
    public function exportAnnualRecap(int $year, array $spgRows, array $monthlyTotals): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Annual Recap ' . $year);

        $sheet->setCellValue('A1', 'REKAPITULASI PENGELUARAN GAJI & INSENTIF SPG TAHUNAN - ' . $year);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $sheet->setCellValue('A3', 'NO');
        $sheet->setCellValue('B3', 'NAMA SPG');
        $sheet->setCellValue('C3', 'OUTLETS VISITED');

        $colIdx = 4;
        foreach ($months as $m) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue($colLetter . '3', $m);
            $colIdx++;
        }
        $totalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
        $sheet->setCellValue($totalColLetter . '3', 'TOTAL TAHUNAN (RP)');

        $sheet->getStyle('A3:' . $totalColLetter . '3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '312E81']
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '818CF8']]],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(28);

        $rowIdx = 4;
        $no = 1;
        foreach ($spgRows as $row) {
            $sheet->setCellValue('A' . $rowIdx, $no++);
            $sheet->setCellValue('B' . $rowIdx, $row['spg_name']);
            $sheet->setCellValue('C' . $rowIdx, $row['outlet_code']);

            $cIdx = 4;
            for ($m = 1; $m <= 12; $m++) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
                $val = $row['months'][$m] ?? 0;
                $sheet->setCellValue($cLetter . $rowIdx, $val);
                $cIdx++;
            }

            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $sheet->setCellValue($cLetter . $rowIdx, $row['annual_total']);
            $sheet->getStyle($cLetter . $rowIdx)->getFont()->setBold(true);

            $sheet->getStyle('A' . $rowIdx . ':' . $totalColLetter . $rowIdx)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);
            $rowIdx++;
        }

        // Total Row
        $sheet->setCellValue('A' . $rowIdx, '');
        $sheet->setCellValue('B' . $rowIdx, 'TOTAL PENGELUARAN BULANAN');
        $sheet->mergeCells('B' . $rowIdx . ':C' . $rowIdx);
        $sheet->getStyle('B' . $rowIdx)->getFont()->setBold(true);

        $cIdx = 4;
        $grandAnnualTotal = 0;
        for ($m = 1; $m <= 12; $m++) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $val = $monthlyTotals[$m] ?? 0;
            $grandAnnualTotal += $val;
            $sheet->setCellValue($cLetter . $rowIdx, $val);
            $sheet->getStyle($cLetter . $rowIdx)->getFont()->setBold(true);
            $cIdx++;
        }
        $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
        $sheet->setCellValue($cLetter . $rowIdx, $grandAnnualTotal);
        $sheet->getStyle($cLetter . $rowIdx)->getFont()->setBold(true)->getColor()->setRGB('DC2626');

        $sheet->getStyle('A' . $rowIdx . ':' . $totalColLetter . $rowIdx)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9']
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '94A3B8']]],
        ]);

        $sheet->freezePane('D4');
        $sheet->getStyle('D4:' . $totalColLetter . $rowIdx)->getNumberFormat()->setFormatCode('#,##0');

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(26);

        $filename = 'Rekap_Tahunan_' . $year . '.xlsx';
        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    /**
     * Generate standard legacy template.
     */
    public function generateSampleTemplate(PayrollTask $task): StreamedResponse
    {
        $dates = $task->getDatesList();
        $spreadsheet = new Spreadsheet();

        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Presensi');
        $sheet1->setCellValue('A1', 'NO');
        $sheet1->setCellValue('B1', 'NAMA SPG');
        $sheet1->setCellValue('C1', 'HOMEBASE OUTLET');

        $colIdx = 4;
        foreach ($dates as $d) {
            $sheet1->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx) . '1', (int)$d->format('d'));
            $colIdx++;
        }

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Omzet');
        $sheet2->setCellValue('A1', 'NO');
        $sheet2->setCellValue('B1', 'NAMA SPG / OUTLET');
        $sheet2->setCellValue('C1', 'TIPE');

        $colIdx = 4;
        foreach ($dates as $d) {
            $sheet2->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx) . '1', (int)$d->format('d'));
            $colIdx++;
        }

        $filename = 'Template_Presensi_Omzet_' . str_replace(' ', '_', $task->period_label) . '.xlsx';
        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    protected function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
