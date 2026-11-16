<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_type',
        'title',
        'period_month',
        'period_year',
        'period_label',
        'start_date',
        'end_date',
        'status',
        'twin_outlet_mode',
        'notes',
        'finished_at',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'period_year' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'finished_at' => 'datetime',
    ];

    public function isSalary(): bool
    {
        return $this->task_type === 'SALARY';
    }

    public function isIncentive(): bool
    {
        return $this->task_type === 'INSENTIF';
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isFinished(): bool
    {
        return $this->status === 'FINISHED';
    }

    public function dateMultipliers(): HasMany
    {
        return $this->hasMany(TaskDateMultiplier::class);
    }

    public function salaryRecords(): HasMany
    {
        return $this->hasMany(SalaryRecord::class);
    }

    public function incentiveRecords(): HasMany
    {
        return $this->hasMany(IncentiveRecord::class);
    }

    /**
     * Get array of Carbon dates in the task working period.
     */
    public function getDatesList(): array
    {
        $period = CarbonPeriod::create($this->start_date, $this->end_date);
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->copy();
        }
        return $dates;
    }

    /**
     * Calculate automatic working period mapping based on task type, target month & year.
     * 
     * SALARY: 21st of (Month - 2) to 20th of (Month - 1)
     * INSENTIF: 1st to End of Month (Month - 1)
     */
    public static function calculateWorkingPeriod(string $taskType, int $month, int $year): array
    {
        $targetDate = Carbon::createFromDate($year, $month, 1);
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $periodLabel = ($monthNames[$month] ?? $targetDate->format('F')) . ' ' . $year;

        if ($taskType === 'SALARY') {
            // Month - 2 at 21st
            $startDate = $targetDate->copy()->subMonths(2)->day(21);
            // Month - 1 at 20th
            $endDate = $targetDate->copy()->subMonth()->day(20);
        } else {
            // Month - 1 full month: 1st to End of Month
            $prevMonth = $targetDate->copy()->subMonth();
            $startDate = $prevMonth->copy()->startOfMonth();
            $endDate = $prevMonth->copy()->endOfMonth();
        }

        return [
            'period_label' => $periodLabel,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
    }
}
