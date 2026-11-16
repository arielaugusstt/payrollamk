<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncentiveRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_task_id',
        'spg_id',
        'assigned_outlet_id',
        'reference_outlet_id',
        'outlets_summary',
        'total_omzet_amk',
        'total_omzet_amkopi',
        'total_omzet',
        'insentif_tier_1',
        'insentif_tier_2',
        'insentif_murni',
        'uang_tambahan',
        'total_insentif',
        'notes',
    ];

    protected $casts = [
        'outlets_summary' => 'array',
        'total_omzet_amk' => 'float',
        'total_omzet_amkopi' => 'float',
        'total_omzet' => 'float',
        'insentif_tier_1' => 'float',
        'insentif_tier_2' => 'float',
        'insentif_murni' => 'float',
        'uang_tambahan' => 'float',
        'total_insentif' => 'float',
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
        return $this->hasMany(IncentiveDailyLog::class)->orderBy('date');
    }

    /**
     * Get effective reference outlet (manual override > dominant outlet > assigned outlet > spg default).
     */
    public function getEffectiveOutletAttribute(): ?Outlet
    {
        return $this->referenceOutlet ?? $this->assignedOutlet ?? ($this->spg ? ($this->spg->defaultOutlet ?? $this->spg->assignedOutlet) : null);
    }
}
