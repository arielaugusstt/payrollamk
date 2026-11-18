<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankPresensiDaily extends Model
{
    use HasFactory;

    protected $table = 'bank_presensi_daily';

    protected $fillable = [
        'period_id',
        'work_date',
        'day_number',
        'spg_id',
        'outlet_id',
        'raw_outlet_text',
    ];

    protected $casts = [
        'day_number' => 'integer',
    ];

    public function setWorkDateAttribute($value): void
    {
        $this->attributes['work_date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string)$value, 0, 10);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(MonthlyDataPeriod::class, 'period_id');
    }

    public function spg(): BelongsTo
    {
        return $this->belongsTo(Spg::class, 'spg_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
