<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerAttendance extends Model
{
    protected $fillable = [
        'worker_id',
        'worker_group_id',
        'date',
        'week_number',
        'year',
        'role',
        'attended',
        'overtime_hours',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'attended' => 'boolean',
        'overtime_hours' => 'decimal:2',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function workerGroup(): BelongsTo
    {
        return $this->belongsTo(WorkerGroup::class);
    }
}
