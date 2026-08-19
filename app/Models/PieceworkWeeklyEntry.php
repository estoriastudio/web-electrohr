<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PieceworkWeeklyEntry extends Model
{
    protected $fillable = [
        'worker_id', 'project_work_id', 'worker_group_id', 'foreman_worker_id', 'position_category_id',
        'week_number', 'year', 'meals_amount', 'total_amount', 'notes',
    ];

    protected $casts = [
        'meals_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function workerGroup(): BelongsTo
    {
        return $this->belongsTo(WorkerGroup::class);
    }

    public function foreman(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'foreman_worker_id');
    }

    public function positionCategory(): BelongsTo
    {
        return $this->belongsTo(PositionCategory::class);
    }

    public function dailyAmounts(): HasMany
    {
        return $this->hasMany(PieceworkDailyAmount::class);
    }
}