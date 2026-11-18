<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveDailyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'incentive_record_id',
        'date',
        'outlet_id',
        'outlet_code',
        'raw_outlet_amk',
        'raw_outlet_kopi',
        'omzet_amk',
        'omzet_amkopi',
        'total_daily_omzet',
        'is_manual_override',
    ];

    protected $casts = [
        'date' => 'date',
        'raw_outlet_amk' => 'float',
        'raw_outlet_kopi' => 'float',
        'omzet_amk' => 'float',
        'omzet_amkopi' => 'float',
        'total_daily_omzet' => 'float',
        'is_manual_override' => 'boolean',
    ];

    public function incentiveRecord(): BelongsTo
    {
        return $this->belongsTo(IncentiveRecord::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
