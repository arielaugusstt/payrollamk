<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyDataPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'has_presensi',
        'has_omzet',
        'presensi_uploaded_at',
        'omzet_uploaded_at',
        'presensi_filename',
        'omzet_filename',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'has_presensi' => 'boolean',
        'has_omzet' => 'boolean',
        'presensi_uploaded_at' => 'datetime',
        'omzet_uploaded_at' => 'datetime',
    ];

    public static function monthName(int $month): string
    {
        $names = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $names[$month] ?? 'Bulan ' . $month;
    }

    public function getMonthNameAttribute(): string
    {
        return self::monthName($this->month);
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->month_name . ' ' . $this->year;
    }

    public function getDaysInMonthAttribute(): int
    {
        return \Carbon\Carbon::createFromDate($this->year, $this->month, 1)->daysInMonth;
    }

    public function presensiDaily(): HasMany
    {
        return $this->hasMany(BankPresensiDaily::class, 'period_id');
    }

    public function omzetDaily(): HasMany
    {
        return $this->hasMany(BankOmzetDaily::class, 'period_id');
    }
}
