<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncentiveSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'threshold_amount',
        'tier_1_rate_above_threshold',
        'tier_2_rate_remaining',
        'below_threshold_flat_rate',
    ];

    protected $casts = [
        'threshold_amount' => 'float',
        'tier_1_rate_above_threshold' => 'float',
        'tier_2_rate_remaining' => 'float',
        'below_threshold_flat_rate' => 'float',
    ];

    public static function current(): self
    {
        return static::first() ?? static::create([
            'threshold_amount' => 5000000,
            'tier_1_rate_above_threshold' => 1.25,
            'tier_2_rate_remaining' => 2.50,
            'below_threshold_flat_rate' => 2.50,
        ]);
    }
}
