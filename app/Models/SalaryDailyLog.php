<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryDailyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_record_id',
        'date',
        'raw_cell_value',
        'is_present',
        'matched_outlet_codes',
        'chosen_outlet_id',
        'outlet_code',
        'multiplier',
        'daily_uang_makan_utama',
        'daily_uang_makan_tambahan',
    ];

    protected $casts = [
        'date' => 'date',
        'is_present' => 'boolean',
        'matched_outlet_codes' => 'array',
        'multiplier' => 'integer',
        'daily_uang_makan_utama' => 'float',
        'daily_uang_makan_tambahan' => 'float',
    ];

    public function getNominalUangMakanAttribute(): float
    {
        return (float)($this->daily_uang_makan_utama + $this->daily_uang_makan_tambahan);
    }

    public function salaryRecord(): BelongsTo
    {
        return $this->belongsTo(SalaryRecord::class);
    }

    public function chosenOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'chosen_outlet_id');
    }
}
