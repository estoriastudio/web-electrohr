<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderMilestone extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'type',
        'concept',
        'payment_condition',
        'is_advance',
        'value_type',
        'value',
        'covered_amount',
        'invoice_date',
        'due_date',
    ];

    protected $casts = [
        'value'             => 'decimal:2',
        'covered_amount'    => 'decimal:2',
        'invoice_date'      => 'date',
        'due_date'          => 'date',
        'is_advance'        => 'boolean',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'milestone_id');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(
            PurchaseOrderInvoice::class,
            'invoice_milestone',
            'purchase_order_milestone_id',
            'purchase_order_invoice_id'
        );
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PurchaseOrderEvidence::class, 'purchase_order_milestone_id');
    }

    /**
     * Monto objetivo real del hito en la moneda de la OC.
     * - value_type = 'fijo':       devuelve value directamente.
     * - value_type = 'porcentaje': devuelve (value / 100) * OC.amount.
     */
    public function getEffectiveAmountAttribute(): float
    {
        if ($this->value_type === 'porcentaje') {
            $orderAmount = $this->relationLoaded('purchaseOrder')
                ? (float) $this->purchaseOrder->amount
                : (float) $this->purchaseOrder()->value('amount');

            return round($orderAmount * (float) $this->value / 100, 2);
        }

        return (float) $this->value;
    }

    public function getProgressPercentAttribute(): float
    {
        $target = $this->effective_amount;

        if ($target <= 0) {
            return 0.0;
        }

        $percent = ((float) $this->covered_amount / $target) * 100;

        return min(100.0, round($percent, 1));
    }

    public function getIsCompleteAttribute(): bool
    {
        $target = $this->effective_amount;

        return $target > 0 && (float) $this->covered_amount >= $target;
    }
}
