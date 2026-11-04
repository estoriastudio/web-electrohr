<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeEntry extends Model
{
    public const SOURCES = ['field', 'hr', 'late'];

    protected $fillable = [
        'worker_id',
        'date',
        'project_work_id',
        'charge_project_work_id',
        'hours',
        'source',
        'set_by',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'integer',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }
}
