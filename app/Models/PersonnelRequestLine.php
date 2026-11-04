<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelRequestLine extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'personnel_request_id',
        'worker_id',
        'status',
        'resolved_by',
        'resolved_at',
        'effective_date',
        'note',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'effective_date' => 'date',
    ];

    public function personnelRequest(): BelongsTo
    {
        return $this->belongsTo(PersonnelRequest::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
