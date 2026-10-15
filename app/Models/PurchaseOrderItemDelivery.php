<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItemDelivery extends Model
{
    protected $fillable = [
        'purchase_order_item_id',
        'evidence_id',
        'delivered_by',
        'quantity',
        'delivered_at',
    ];

    protected $casts = [
        'quantity'     => 'decimal:4',
        'delivered_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderEvidence::class, 'evidence_id');
    }

    public function deliveredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }
}
