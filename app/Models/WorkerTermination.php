<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerTermination extends Model
{
    protected $fillable = [
        'worker_id',
        'project_work_id',
        'position_category_id',
        'salary',
        'termination_type',
        'reason',
        'termination_date',
        'notes',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'termination_date' => 'date',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function positionCategory(): BelongsTo
    {
        return $this->belongsTo(PositionCategory::class);
    }
}