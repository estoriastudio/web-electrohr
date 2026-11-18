<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestChangeNote extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'requested_by',
        'text',
        'is_commercial',
        'direction_status',
        'direction_decided_by',
        'direction_decided_at',
        'direction_note',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'is_commercial' => 'boolean',
        'direction_decided_at' => 'datetime',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function directionDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direction_decided_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /** Ajuste comercial que todavía espera la autorización de Dirección (Fase 4). */
    public function awaitsDirection(): bool
    {
        return $this->is_commercial && $this->direction_status === 'pending';
    }
}
