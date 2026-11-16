<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_task_id',
        'spg_id',
        'assigned_outlet_id',
        'reference_outlet_id',
        'outlets_summary',
        'total_hari_masuk',
        'attendance_percentage',
        'uang_makan_utama',
        'uang_makan_tambahan',
        'base_salary_outlet',
        'base_salary_recommended',
        'policy_100_percent_override',
        'base_salary_final',
        'uang_pulsa',
        'uang_tambahan',
        'total_salary',
        'notes',
    ];

    protected $casts = [
        'outlets_summary' => 'array',
        'total_hari_masuk' => 'integer',
        'attendance_percentage' => 'float',
        'uang_makan_utama' => 'float',
        'uang_makan_tambahan' => 'float',
        'base_salary_outlet' => 'float',
        'base_salary_recommended' => 'float',
        'policy_100_percent_override' => 'boolean',
        'base_salary_final' => 'float',
        'uang_pulsa' => 'float',
        'uang_tambahan' => 'float',
        'total_salary' => 'float',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(PayrollTask::class, 'payroll_task_id');
    }

    public function spg(): BelongsTo
    {
        return $this->belongsTo(Spg::class);
    }

    public function assignedOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'assigned_outlet_id');
    }

    public function referenceOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'reference_outlet_id');
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(SalaryDailyLog::class)->orderBy('date');
    }

    /**
     * Get effective reference outlet (manual override > dominant outlet > assigned outlet > spg default).
     */
    public function getEffectiveOutletAttribute(): ?Outlet
    {
        return $this->referenceOutlet ?? $this->assignedOutlet ?? ($this->spg ? $this->spg->defaultOutlet : null);
    }
}
