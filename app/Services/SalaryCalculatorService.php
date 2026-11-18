<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\SalaryDailyLog;
use App\Models\SalaryRecord;

class SalaryCalculatorService
{
    public const BENCHMARK_DAYS = 26;
    public const THRESHOLD_PERCENT = 40.0;

    /**
     * Recalculate a single SalaryRecord and all its daily logs.
     * Dynamic Daily Outlet Logic:
     * - Each day's meal allowance is taken from THAT DATE'S worked outlet (or custom_nominal_override if set).
     * - Monthly Gaji Pokok and Uang Pulsa are determined by the Dominant Outlet (or manual reference_outlet_id).
     */
    public function calculateRecord(SalaryRecord $record, bool $save = true): SalaryRecord
    {
        $record->loadMissing(['spg.defaultOutlet', 'dailyLogs.chosenOutlet', 'task.dateMultipliers', 'referenceOutlet']);

        $multipliersMap = $record->task->dateMultipliers->keyBy(fn($m) => $m->date->format('Y-m-d'));

        $totalHariMasuk = 0;
        $totalUangMakanUtama = 0.0;
        $totalUangMakanTambahan = 0.0;
        $outletVisitsCount = []; // outlet_id => count
        $outletObjects = []; // outlet_id => Outlet model

        foreach ($record->dailyLogs as $log) {
            $dateStr = $log->date->format('Y-m-d');
            $multModel = $multipliersMap->get($dateStr);
            $multiplier = $multModel ? (int)$multModel->multiplier : (int)$log->multiplier;
            if ($multiplier < 1) {
                $multiplier = 1;
            }
            $log->multiplier = $multiplier;

            $hasCustomOverride = $log->custom_nominal_override !== null && (float)$log->custom_nominal_override > 0;

            if (($log->is_present && $log->chosenOutlet) || $hasCustomOverride) {
                $totalHariMasuk++;
                if (!$log->is_present && $hasCustomOverride) {
                    $log->is_present = true;
                }

                if ($log->chosenOutlet) {
                    $workOutlet = $log->chosenOutlet;
                    $log->outlet_code = $workOutlet->outlet_code;
                    $outletVisitsCount[$workOutlet->id] = ($outletVisitsCount[$workOutlet->id] ?? 0) + 1;
                    $outletObjects[$workOutlet->id] = $workOutlet;
                }

                if ($log->custom_nominal_override !== null) {
                    $calcUtama = (float)$log->custom_nominal_override;
                    $calcTambahan = 0.0;
                } elseif ($log->chosenOutlet) {
                    $dailyUtamaRate = (float)$log->chosenOutlet->uang_makan_utama;
                    $dailyTambahanRate = (float)$log->chosenOutlet->uang_makan_tambahan;

                    $calcUtama = $dailyUtamaRate * $multiplier;
                    $calcTambahan = $dailyTambahanRate * $multiplier;
                } else {
                    $calcUtama = 0.0;
                    $calcTambahan = 0.0;
                }

                $log->daily_uang_makan_utama = $calcUtama;
                $log->daily_uang_makan_tambahan = $calcTambahan;

                $totalUangMakanUtama += $calcUtama;
                $totalUangMakanTambahan += $calcTambahan;
            } else {
                $log->daily_uang_makan_utama = 0;
                $log->daily_uang_makan_tambahan = 0;
                if (!$log->is_present) {
                    $log->chosen_outlet_id = null;
                    $log->outlet_code = null;
                    $log->custom_nominal_override = null;
                }
            }

            if ($save) {
                $log->save();
            }
        }

        // Determine Outlets Visited summary: e.g. [{"code": "TP-2", "days": 18, "id": 1}, ...]
        $outletsSummary = [];
        $dominantOutlet = null;
        $maxDays = -1;
        $highestPokokInTie = -1;
        $highestUtamaInTie = -1;

        foreach ($outletVisitsCount as $outletId => $daysCount) {
            $outObj = $outletObjects[$outletId];
            $outletsSummary[] = [
                'id' => $outletId,
                'code' => $outObj->outlet_code,
                'name' => $outObj->outlet_name,
                'days' => $daysCount,
            ];

            // Resolve Dominant Outlet: most days worked, tie-breaker = highest rate
            $pokokVal = (float)$outObj->gaji_pokok;
            $utamaVal = (float)$outObj->uang_makan_utama;
            if (
                $daysCount > $maxDays ||
                ($daysCount === $maxDays && ($pokokVal > $highestPokokInTie || ($pokokVal === $highestPokokInTie && $utamaVal > $highestUtamaInTie)))
            ) {
                $maxDays = $daysCount;
                $highestPokokInTie = $pokokVal;
                $highestUtamaInTie = $utamaVal;
                $dominantOutlet = $outObj;
            }
        }

        // Sort visited outlets by days descending
        usort($outletsSummary, fn($a, $b) => $b['days'] <=> $a['days']);

        // Determine Reference Outlet for Gaji Pokok & Uang Pulsa:
        // Manual override > Dominant Outlet > SPG Default Outlet > First Available Outlet
        $effectiveReferenceOutlet = null;
        if ($record->reference_outlet_id) {
            $effectiveReferenceOutlet = Outlet::find($record->reference_outlet_id);
        }
        if (!$effectiveReferenceOutlet) {
            if ($dominantOutlet) {
                $effectiveReferenceOutlet = $dominantOutlet;
                $record->reference_outlet_id = $dominantOutlet->id;
            } elseif ($record->spg && $record->spg->defaultOutlet) {
                $effectiveReferenceOutlet = $record->spg->defaultOutlet;
                $record->reference_outlet_id = $effectiveReferenceOutlet->id;
            } elseif (!empty($outletObjects)) {
                $effectiveReferenceOutlet = reset($outletObjects);
                $record->reference_outlet_id = $effectiveReferenceOutlet->id;
            }
        }

        $outletBaseSalary = (float)($effectiveReferenceOutlet ? $effectiveReferenceOutlet->gaji_pokok : 0);
        $outletPulsa = ($totalHariMasuk > 0 || $record->policy_100_percent_override)
            ? (float)($effectiveReferenceOutlet ? $effectiveReferenceOutlet->uang_pulsa : 0)
            : 0.0;

        // Attendance Percentage based on benchmark 26 days
        $attendancePercentage = round(($totalHariMasuk / self::BENCHMARK_DAYS) * 100, 2);

        // 40% threshold logic: >= (0.40 * 26) i.e. >= 10.4 days (11+ days)
        $meetsThreshold = $totalHariMasuk >= (0.40 * self::BENCHMARK_DAYS);

        if ($meetsThreshold) {
            $recommendedBaseSalary = $outletBaseSalary;
        } else {
            $recommendedBaseSalary = (float)round(($outletBaseSalary / self::BENCHMARK_DAYS) * $totalHariMasuk);
        }

        // Policy 100% full override check
        if ($record->policy_100_percent_override || $meetsThreshold) {
            $finalBaseSalary = $outletBaseSalary;
        } else {
            $finalBaseSalary = $recommendedBaseSalary;
        }

        $record->total_hari_masuk = $totalHariMasuk;
        $record->outlets_summary = $outletsSummary;
        $record->attendance_percentage = $attendancePercentage;
        $record->uang_makan_utama = $totalUangMakanUtama;
        $record->uang_makan_tambahan = $totalUangMakanTambahan;
        $record->base_salary_outlet = $outletBaseSalary;
        $record->base_salary_recommended = $recommendedBaseSalary;
        $record->base_salary_final = $finalBaseSalary;
        $record->uang_pulsa = $outletPulsa;
        $record->total_salary = $totalUangMakanUtama + $totalUangMakanTambahan + $finalBaseSalary + $outletPulsa + (float)$record->uang_tambahan;

        if ($save) {
            $record->save();
        }

        return $record;
    }

    /**
     * Resolve cell text to determine presence and outlet for that day.
     * Guaranteed 1 date = 1 outlet for that SPG.
     */
    public function resolveCellPresenceAndOutlet(?string $cellValue, ?Outlet $fallbackOutlet, $allOutletsMap, $aliasMap): array
    {
        if ($cellValue === null) {
            return [
                'is_present' => false,
                'matched_codes' => [],
                'chosen_outlet_id' => null,
                'outlet_code' => null,
            ];
        }

        $clean = trim((string)$cellValue);
        if ($clean === '' || $clean === '-' || strtoupper($clean) === 'OFF' || strtoupper($clean) === 'LIBUR' || strtoupper($clean) === 'X') {
            return [
                'is_present' => false,
                'matched_codes' => [],
                'chosen_outlet_id' => null,
                'outlet_code' => null,
            ];
        }

        $tokens = preg_split('/[\s,\/]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $matchedOutlets = [];

        foreach ($tokens as $token) {
            $upperToken = strtoupper(trim($token));
            if (isset($allOutletsMap[$upperToken])) {
                $matchedOutlets[$allOutletsMap[$upperToken]->id] = $allOutletsMap[$upperToken];
                continue;
            }

            $lowerToken = strtolower(trim($token));
            if (isset($aliasMap[$lowerToken])) {
                $outlet = $aliasMap[$lowerToken];
                $matchedOutlets[$outlet->id] = $outlet;
            }
        }

        if (empty($matchedOutlets)) {
            $chosenId = $fallbackOutlet ? $fallbackOutlet->id : null;
            $chosenCode = $fallbackOutlet ? $fallbackOutlet->outlet_code : $clean;
            return [
                'is_present' => true,
                'matched_codes' => [$chosenCode],
                'chosen_outlet_id' => $chosenId,
                'outlet_code' => $chosenCode,
            ];
        }

        $chosenOutlet = null;
        $highestConstant = -1;
        $matchedCodes = [];

        foreach ($matchedOutlets as $outlet) {
            $matchedCodes[] = $outlet->outlet_code;
            $val = (float)$outlet->uang_makan_utama;
            if ($val > $highestConstant) {
                $highestConstant = $val;
                $chosenOutlet = $outlet;
            }
        }

        return [
            'is_present' => true,
            'matched_codes' => array_values(array_unique($matchedCodes)),
            'chosen_outlet_id' => $chosenOutlet ? $chosenOutlet->id : null,
            'outlet_code' => $chosenOutlet ? $chosenOutlet->outlet_code : null,
        ];
    }
}
