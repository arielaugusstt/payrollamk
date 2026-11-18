<?php

namespace App\Services;

use App\Models\IncentiveRecord;
use App\Models\IncentiveSetting;

class IncentiveCalculatorService
{
    /**
     * Recalculate a single IncentiveRecord and all its daily logs.
     * Dynamic Daily Outlet:
     * - Accumulates all daily omzet across multiple outlets worked by that SPG.
     * - Summarizes outlets visited / omzet generated per outlet.
     */
    public function calculateRecord(IncentiveRecord $record, ?IncentiveSetting $settings = null, bool $save = true): IncentiveRecord
    {
        $settings = $settings ?? IncentiveSetting::current();
        $record->loadMissing(['dailyLogs.outlet', 'spg.defaultOutlet', 'referenceOutlet']);

        $totalAmk = 0.0;
        $totalAmkopi = 0.0;
        $outletOmzetMap = []; // outlet_id => ['code' => '...', 'name' => '...', 'omzet' => float, 'days' => int]

        foreach ($record->dailyLogs as $log) {
            $dailyTotal = (float)$log->omzet_amk + (float)$log->omzet_amkopi;
            $log->total_daily_omzet = $dailyTotal;

            $totalAmk += (float)$log->omzet_amk;
            $totalAmkopi += (float)$log->omzet_amkopi;

            if ($log->outlet) {
                $outId = $log->outlet->id;
                if (!isset($outletOmzetMap[$outId])) {
                    $outletOmzetMap[$outId] = [
                        'id' => $outId,
                        'code' => $log->outlet->outlet_code,
                        'name' => $log->outlet->outlet_name,
                        'omzet' => 0.0,
                        'days' => 0,
                    ];
                }
                $outletOmzetMap[$outId]['omzet'] += $dailyTotal;
                $outletOmzetMap[$outId]['days'] += 1;
            }

            if ($save) {
                $log->save();
            }
        }

        $totalOmzet = $totalAmk + $totalAmkopi;

        // Build outlets_summary array
        $outletsSummary = array_values($outletOmzetMap);
        usort($outletsSummary, fn($a, $b) => $b['omzet'] <=> $a['omzet']);

        // Determine dominant outlet by highest omzet
        if (!empty($outletsSummary) && !$record->reference_outlet_id) {
            $record->reference_outlet_id = $outletsSummary[0]['id'];
        } elseif (!$record->reference_outlet_id && $record->spg && $record->spg->default_outlet_id) {
            $record->reference_outlet_id = $record->spg->default_outlet_id;
        }

        $threshold = (float)$settings->threshold_amount; // 5,000,000
        $tier1Rate = (float)$settings->tier_1_rate_above_threshold / 100.0; // 1.25% = 0.0125
        $tier2Rate = (float)$settings->tier_2_rate_remaining / 100.0; // 2.5% = 0.025
        $belowRate = (float)$settings->below_threshold_flat_rate / 100.0; // 2.5% = 0.025

        $insentifTier1 = 0.0;
        $insentifTier2 = 0.0;
        $insentifMurni = 0.0;

        if ($totalOmzet > $threshold) {
            // Tier 1: First 5,000,000 * 1.25% = 62,500
            $insentifTier1 = round($threshold * $tier1Rate);

            // Tier 2: Remaining * 2.5%
            $remainder = $totalOmzet - $threshold;
            $insentifTier2 = round($remainder * $tier2Rate);

            $insentifMurni = $insentifTier1 + $insentifTier2;
        } else {
            // Below or equal threshold: Flat 2.5% of total omzet
            $insentifMurni = round($totalOmzet * $belowRate);
        }

        $record->outlets_summary = $outletsSummary;
        $record->total_omzet_amk = $totalAmk;
        $record->total_omzet_amkopi = $totalAmkopi;
        $record->total_omzet = $totalOmzet;
        $record->insentif_tier_1 = $insentifTier1;
        $record->insentif_tier_2 = $insentifTier2;
        $record->insentif_murni = $insentifMurni;
        $record->total_insentif = $insentifMurni + (float)$record->uang_tambahan;

        if ($save) {
            $record->save();
        }

        return $record;
    }
}
