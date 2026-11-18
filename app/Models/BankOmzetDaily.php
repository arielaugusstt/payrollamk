<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankOmzetDaily extends Model
{
    use HasFactory;

    protected $table = 'bank_omzet_daily';

    protected $fillable = [
        'period_id',
        'omzet_date',
        'day_number',
        'outlet_id',
        'omzet_amk',
        'omzet_amkopi',
        'total_omzet',
    ];

    protected $casts = [
        'day_number' => 'integer',
        'omzet_amk' => 'float',
        'omzet_amkopi' => 'float',
        'total_omzet' => 'float',
    ];

    public function setOmzetDateAttribute($value): void
    {
        $this->attributes['omzet_date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string)$value, 0, 10);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(MonthlyDataPeriod::class, 'period_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
