<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'concept_id',
        'code',
        'description',
        'unit',
        'requested_quantity',
        'purchase_quantity',
        'file_path',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Estatus de OC que bloquean la edición del concepto en la SOLCOM.
    public const LOCKING_ORDER_STATUSES = ['emitida', 'autorizada'];

    // Pendiente: la cantidad a comprar aún no se hereda por completo a OC vigentes (no eliminadas).
    public function scopePendingInheritance(Builder $query): Builder
    {
        return $query->whereRaw(
            'purchase_request_items.purchase_quantity > COALESCE((
                SELECT SUM(poi.quantity)
                FROM purchase_order_items poi
                INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
                WHERE poi.purchase_request_item_id = purchase_request_items.id
                  AND po.deleted_at IS NULL
            ), 0)'
        );
    }

    /**
     * OC vigentes (no eliminadas) a las que se heredó este concepto.
     * Requiere purchaseOrderItems.purchaseOrder cargados o los carga bajo demanda.
     */
    public function inheritedOrders(): Collection
    {
        $this->loadMissing('purchaseOrderItems.purchaseOrder');

        return $this->purchaseOrderItems
            ->map(fn (PurchaseOrderItem $orderItem) => $orderItem->purchaseOrder)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function getInheritedOrderAttribute(): ?PurchaseOrder
    {
        return $this->inheritedOrders()->first();
    }

    // Cantidad ya fincada a OC vigentes.
    public function getInheritedQuantityAttribute(): float
    {
        $this->loadMissing('purchaseOrderItems.purchaseOrder');

        return round(
            (float) $this->purchaseOrderItems
                ->filter(fn (PurchaseOrderItem $orderItem) => $orderItem->purchaseOrder !== null)
                ->sum(fn (PurchaseOrderItem $orderItem) => (float) $orderItem->quantity),
            4
        );
    }

    // Cantidad no fincada a una OC.
    public function getRemainingQuantityAttribute(): float
    {
        return max(0.0, round((float) $this->purchase_quantity - $this->inherited_quantity, 4));
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->remaining_quantity > 0;
    }

    // Fincado por completo: ya existe en una OC y no falta cantidad.
    public function getIsInheritedAttribute(): bool
    {
        return $this->inheritedOrders()->isNotEmpty() && $this->remaining_quantity <= 0;
    }

    public function getIsPartiallyInheritedAttribute(): bool
    {
        return $this->inherited_quantity > 0 && $this->remaining_quantity > 0;
    }

    // Bloqueado: fincado en una OC Emitida o Autorizada.
    public function lockingOrder(): ?PurchaseOrder
    {
        return $this->inheritedOrders()
            ->first(fn (PurchaseOrder $order) => in_array($order->status, self::LOCKING_ORDER_STATUSES, true));
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->lockingOrder() !== null;
    }
}
