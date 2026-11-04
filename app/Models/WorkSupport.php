<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSupport extends Model
{
    public const FRACTIONS = ['half' => 'Medio día', 'full' => 'Día completo'];

    protected $fillable = [
        'worker_id',
        'date',
        'from_project_work_id',
        'to_project_work_id',
        'fraction',
        'set_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function toProjectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class, 'to_project_work_id');
    }
}
