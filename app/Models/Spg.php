<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spg extends Model
{
    use HasFactory;

    protected $fillable = [
        'spg_name',
        'assigned_outlet_id',
        'default_outlet_id',
        'is_active',
        'aliases',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'aliases' => 'array',
    ];

    public function defaultOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'default_outlet_id');
    }

    public function assignedOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'assigned_outlet_id');
    }

    public function salaryRecords(): HasMany
    {
        return $this->hasMany(SalaryRecord::class);
    }

    public function incentiveRecords(): HasMany
    {
        return $this->hasMany(IncentiveRecord::class);
    }
}
