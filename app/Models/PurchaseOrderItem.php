<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'purchase_request_item_id',
        'concept_id',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'delivery_date',
        'delivery_due_date',
        'delivered_quantity',
        'delivered_at',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'delivered_quantity' => 'decimal:4',
        'unit_price'        => 'decimal:8',
        'delivery_due_date' => 'date',
        'delivered_at'      => 'datetime',
    ];

    public function getIsDeliveredAttribute(): bool
    {
        return $this->delivered_at !== null;
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemDelivery::class)->orderBy('delivered_at')->orderBy('id');
    }

    public function getPendingQuantityAttribute(): float
    {
        return max(0.0, round((float) $this->quantity - (float) $this->delivered_quantity, 4));
    }

    // Avance de entrega del concepto entre 0 y 1.
    public function getDeliveryFractionAttribute(): float
    {
        if ($this->delivered_at !== null) {
            return 1.0;
        }

        $quantity = (float) $this->quantity;

        return $quantity > 0 ? min(1.0, (float) $this->delivered_quantity / $quantity) : 0.0;
    }

    /**
     * Recalcula la cantidad entregada a partir del historial de entregas y marca
     * el concepto como entregado cuando se cubre la cantidad total.
     */
    public function recalculateDelivery(): void
    {
        $delivered = round((float) $this->deliveries()->sum('quantity'), 4);
        $complete = $delivered > 0 && $delivered >= round((float) $this->quantity, 4);

        $this->forceFill([
            'delivered_quantity' => $delivered,
            'delivered_at' => $complete
                ? ($this->delivered_at ?? $this->deliveries()->max('delivered_at') ?? now())
                : null,
        ])->save();
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }
}
