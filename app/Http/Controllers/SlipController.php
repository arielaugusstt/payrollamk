<?php

namespace App\Http\Controllers;

use App\Models\SalaryRecord;
use Illuminate\Http\Request;

class SlipController extends Controller
{
    public function show(SalaryRecord $record)
    {
        $record->load([
            'task.dateMultipliers',
            'spg',
            'assignedOutlet',
            'dailyLogs.chosenOutlet'
        ]);

        $task = $record->task;
        $multipliersMap = $task->dateMultipliers->keyBy(fn($m) => $m->date->format('Y-m-d'));
        $dailyLogs = $record->dailyLogs;

        // Header string: e.g. "21 NOV - 20 DEC"
        $periodHeader = strtoupper($task->start_date->format('d M') . ' - ' . $task->end_date->format('d M'));

        return view('slips.modal', compact('record', 'task', 'multipliersMap', 'dailyLogs', 'periodHeader'));
    }
}
