<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyIncentiveLetter extends Model
{
    public const CATEGORIES = ['A', 'B', 'C', 'D', 'NA'];

    protected $fillable = [
        'worker_id',
        'payroll_period_start',
        'project_work_id',
        'category',
        'set_by',
    ];

    protected $casts = [
        'payroll_period_start' => 'date',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
