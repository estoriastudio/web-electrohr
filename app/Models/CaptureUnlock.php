<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptureUnlock extends Model
{
    protected $fillable = [
        'project_work_id',
        'date',
        'reason',
        'granted_by',
        'expires_at',
        'active',
    ];

    protected $casts = [
        'date' => 'date',
        'expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** Habilitación activa: no retirada y sin vencer. */
    public function scopeUsable($query)
    {
        return $query->where('active', true)->where('expires_at', '>', now());
    }
}
