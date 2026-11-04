<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonnelRequest extends Model
{
    public const STATUSES = [
        'draft' => 'Borrador',
        'sent' => 'Enviada a RH',
        'closed' => 'Cerrada',
    ];

    /** Transiciones permitidas del estatus. */
    public const TRANSITIONS = [
        'draft' => ['sent'],
        'sent' => ['closed'],
        'closed' => [],
    ];

    protected $fillable = [
        'project_work_id',
        'requested_by',
        'start_date',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PersonnelRequestLine::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
