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

    // Concepto heredado: tiene una partida en una OC no eliminada.
    public function scopeInherited(Builder $query): Builder
    {
        return $query->whereHas('purchaseOrderItems.purchaseOrder');
    }

    public function scopePendingInheritance(Builder $query): Builder
    {
        return $query->whereDoesntHave('purchaseOrderItems.purchaseOrder');
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

    public function getIsInheritedAttribute(): bool
    {
        return $this->inheritedOrders()->isNotEmpty();
    }

    // Bloqueado: heredado en una OC Emitida o Autorizada.
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
