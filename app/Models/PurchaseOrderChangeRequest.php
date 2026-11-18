<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de cambio o cancelación de una OC autorizada (Fase 4). La pide Compras y la
 * autoriza o rechaza Dirección (`purchase_orders.authorize`).
 */
class PurchaseOrderChangeRequest extends Model
{
    public const TYPE_CHANGE = 'change';

    public const TYPE_CANCEL = 'cancel';

    public const TYPES = [self::TYPE_CHANGE, self::TYPE_CANCEL];

    protected $fillable = [
        'purchase_order_id',
        'type',
        'reason',
        'status',
        'requested_by',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_CANCEL ? 'Cancelación' : 'Cambio';
    }
}
