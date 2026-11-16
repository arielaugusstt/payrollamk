<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDateMultiplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_task_id',
        'date',
        'multiplier',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'multiplier' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(PayrollTask::class, 'payroll_task_id');
    }
}
