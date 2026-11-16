<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_code',
        'outlet_name',
        'gaji_pokok',
        'uang_makan_utama',
        'uang_makan_tambahan',
        'uang_pulsa',
        'is_active',
    ];

    protected $casts = [
        'gaji_pokok' => 'float',
        'uang_makan_utama' => 'float',
        'uang_makan_tambahan' => 'float',
        'uang_pulsa' => 'float',
        'is_active' => 'boolean',
    ];

    public function aliases(): HasMany
    {
        return $this->hasMany(OutletAlias::class);
    }

    public function spgs(): HasMany
    {
        return $this->hasMany(Spg::class, 'assigned_outlet_id');
    }

    public function salaryRecords(): HasMany
    {
        return $this->hasMany(SalaryRecord::class, 'assigned_outlet_id');
    }

    public function incentiveRecords(): HasMany
    {
        return $this->hasMany(IncentiveRecord::class, 'assigned_outlet_id');
    }
}
