<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateOvertimeRequest extends Model
{
    public const STATUSES = [
        'pending' => 'Por aprobar',
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
    ];

    protected $fillable = [
        'worker_id',
        'project_work_id',
        'date',
        'hours',
        'reason',
        'status',
        'requested_by',
        'resolved_by',
        'resolved_at',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
