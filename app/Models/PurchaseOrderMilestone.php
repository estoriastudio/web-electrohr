<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PurchaseOrderMilestone extends Model
{
    public const CREDIT_START_INVOICE = 'invoice_validation';
    public const CREDIT_START_FULFILLMENT = 'fulfillment_validated';

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
        'delivery_confirmed_at',
        'delivery_confirmed_by',
        'credit_start',
        'credit_days',
        'split_from_milestone_id',
    ];

    protected $casts = [
        'value'             => 'decimal:2',
        'covered_amount'    => 'decimal:2',
        'invoice_date'      => 'date',
        'due_date'          => 'date',
        'is_advance'        => 'boolean',
        'delivery_confirmed_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_milestone_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PurchaseOrderMilestoneAdjustment::class, 'milestone_id')->latest('id');
    }

    public function ensurePaymentTotalWithinAmount(float $total, string $errorKey = 'amount'): void
    {
        if (round($total * 100) > round($this->effective_amount * 100)) {
            throw ValidationException::withMessages([
                $errorKey => 'El total de pagos no rechazados no puede exceder el monto del hito ('
                    . number_format($this->effective_amount, 2) . ').',
            ]);
        }
    }

    public function ensureReactivationFits(Payment $payment): void
    {
        if ($payment->status === 'rechazado') {
            $this->ensurePaymentTotalWithinAmount(
                (float) $this->payments()->whereIn('status', Payment::COMMITTED_STATUSES)->sum('amount')
                    + (float) $payment->amount,
                'status',
            );
        }
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

    public function deliveries(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemDelivery::class, 'milestone_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PurchaseOrderEvidence::class, 'purchase_order_milestone_id');
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(PurchaseOrderMilestoneFulfillment::class, 'purchase_order_milestone_id');
    }

    /**
     * Tipo con el que se cumplió el hito: entregas ligadas = recepción física;
     * con registro = el tipo del registro; si no, null (sin tipo).
     */
    public function getFulfillmentTypeAttribute(): ?string
    {
        $hasDeliveries = $this->relationLoaded('deliveries')
            ? $this->deliveries->isNotEmpty()
            : $this->deliveries()->exists();

        if ($hasDeliveries) {
            return PurchaseOrderMilestoneFulfillment::TYPE_PHYSICAL;
        }

        $fulfillment = $this->relationLoaded('fulfillments')
            ? $this->fulfillments->first()
            : $this->fulfillments()->first();

        return $fulfillment?->fulfillment_type;
    }

    public function getFulfillmentTypeLabelAttribute(): ?string
    {
        $type = $this->fulfillment_type;

        return $type ? PurchaseOrderMilestoneFulfillment::TYPE_LABELS[$type] : null;
    }

    /**
     * Monto objetivo real del hito en la moneda de la OC.
     * - value_type = 'fijo':       devuelve value directamente.
     * - value_type = 'porcentaje': devuelve (value / 100) * total neto de la OC.
     */
    public function getEffectiveAmountAttribute(): float
    {
        if ($this->value_type === 'porcentaje') {
            $purchaseOrder = $this->relationLoaded('purchaseOrder')
                ? $this->purchaseOrder
                : $this->purchaseOrder()->with(['items', 'milestones'])->first();
            if ($purchaseOrder
                && (!$purchaseOrder->relationLoaded('items') || !$purchaseOrder->relationLoaded('milestones'))) {
                $purchaseOrder->loadMissing(['items', 'milestones']);
            }
            $orderAmount = (float) ($purchaseOrder?->total_with_iva ?? 0);

            $percentageMilestones = $purchaseOrder?->relationLoaded('milestones')
                ? $purchaseOrder->milestones->where('value_type', 'porcentaje')->sortBy('id')->values()
                : collect();
            $percentageTotal = (float) $percentageMilestones->sum('value');
            $lastPercentageMilestone = $percentageMilestones->last();
            $isLastPercentageMilestone = $lastPercentageMilestone === $this
                || ($this->getKey() !== null && $lastPercentageMilestone?->getKey() === $this->getKey());

            if ($percentageMilestones->isNotEmpty()
                && abs($percentageTotal - 100) < 0.0001
                && $isLastPercentageMilestone) {
                $previousAmounts = $percentageMilestones
                    ->slice(0, -1)
                    ->sum(fn (self $milestone) => round($orderAmount * (float) $milestone->value / 100, 2));

                return round($orderAmount - $previousAmounts, 2);
            }

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
