<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class PurchaseOrder extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'parent_id',
        'folio',
        'purchase_request_id',
        'type',
        'is_destajo',
        'supplier_id',
        'mobile_asset_id',
        'project_id',
        'project_work_id',
        'project',
        'site',
        'currency',
        'amount',
        'tax_rate',
        'isr_rate',
        'retention_iva_rate',
        'retention_isr_rate',
            'cedular_rate',
        'status',
        'is_delivered',
        'delivery_status',
        'delivery_evidence',
        'recurrence_type',
        'recurrence_frequency',
        'recurrence_start_date',
        'recurrence_end_date',
        'observations',
        'buyer_id',
        'elaborated_by',
        'attorney_name',
        'supplier_signatory',
        'authorized_signatory',
        'deletion_comment',
        'archived_at',
        'closed_at',
        'closed_by',
        'closure_note',
    ];

    protected $casts = [
        'amount'                => 'decimal:2',
        'tax_rate'              => 'decimal:2',
        'isr_rate'              => 'decimal:4',
        'retention_iva_rate'    => 'decimal:4',
        'retention_isr_rate'    => 'decimal:4',
            'cedular_rate'          => 'decimal:4',
        'is_destajo'            => 'boolean',
        'is_delivered'          => 'boolean',
        'recurrence_start_date' => 'date',
        'recurrence_end_date'   => 'date',
        'observations'          => 'array',
        'archived_at'           => 'datetime',
        'closed_at'             => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('purchase_orders.closed_at');
    }

    public function scopeClosed($query)
    {
        return $query->whereNotNull('purchase_orders.closed_at');
    }

    public function getIsClosedAttribute(): bool
    {
        return $this->closed_at !== null;
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Una OC cerrada no admite cambios en hitos, pagos, entregas ni facturas hasta reabrirla.
     */
    public function ensureOpen(): void
    {
        if ($this->is_closed) {
            throw ValidationException::withMessages([
                'purchase_order' => 'La orden de compra #' . ($this->folio ?? $this->id) . ' está cerrada. Reábrela para modificarla.',
            ]);
        }
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('purchase_orders.buyer_id', $userId);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function mobileAsset(): BelongsTo
    {
        return $this->belongsTo(MobileAsset::class);
    }

    public function projectRelation(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workRelation(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class, 'project_work_id');
    }

    public function projectWorks(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjectWork::class,
            'purchase_order_project_works',
            'purchase_order_id',
            'project_work_id'
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'parent_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PurchaseOrderMilestone::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, PurchaseOrderMilestone::class, 'purchase_order_id', 'milestone_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseOrderInvoice::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PurchaseOrderEvidence::class)->latest();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Cada concepto aporta su avance (cantidad entregada / cantidad pedida) en partes iguales.
    public function getDeliveryProgressAttribute(): int
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 0;
        }

        return (int) round($items->avg(fn ($item) => $item->delivery_fraction) * 100);
    }

    /**
     * Recalcula el estatus de entrega según las cantidades entregadas de cada concepto.
     * Con $preserveLegacyPartial, una OC en "parcial" sin entregas registradas conserva su estatus.
     */
    public function syncDeliveryStatus(bool $preserveLegacyPartial = true): void
    {
        $items = $this->items()->get();
        $items->each->recalculateDelivery();

        $total = $items->count();
        $completed = $items->whereNotNull('delivered_at')->count();
        $hasDeliveries = $items->contains(fn ($item) => (float) $item->delivered_quantity > 0);

        if ($total > 0 && $completed === $total) {
            $status = 'entregado';
        } elseif ($hasDeliveries) {
            $status = 'parcial';
        } elseif ($preserveLegacyPartial && $this->delivery_status === 'parcial') {
            $status = 'parcial';
        } else {
            $status = 'por_entregar';
        }

        $this->update([
            'delivery_status' => $status,
            'is_delivered' => $status === 'entregado',
        ]);
        $this->unsetRelation('items');
    }

    public function annex(): HasOne
    {
        return $this->hasOne(PurchaseOrderAnnex::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function getNextDueDateAttribute(): ?string
    {
        $milestone = $this->milestones()
            ->whereNotNull('due_date')
            ->whereColumn('covered_amount', '<', 'value')
            ->orderBy('due_date')
            ->first();

        return $milestone?->due_date?->format('d/m/Y');
    }

    public function getSaldoCubiertoAttribute(): float
    {
        return (float) $this->payments()
            ->where('status', 'pagado')
            ->sum('amount');
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
    }

    public function getIvaAttribute(): float
    {
        return $this->getTaxAmountAttribute();
    }

    public function getTaxAmountAttribute(): float
    {
        $rate = (float) ($this->tax_rate ?? 0);
        return round($this->subtotal * ($rate / 100), 2);
    }

    public function getIsrAmountAttribute(): float
    {
        $rate = (float) ($this->isr_rate ?? 0);
        return round($this->subtotal * ($rate / 100), 2);
    }

    public function getRetentionIvaAmountAttribute(): float
    {
        $rate = (float) ($this->retention_iva_rate ?? 0);
        return round($this->subtotal * ($rate / 100), 2);
    }

    public function getRetentionIsrAmountAttribute(): float
    {
        $rate = (float) ($this->retention_isr_rate ?? 0);
        return round($this->subtotal * ($rate / 100), 2);
    }

    public function getCedularAmountAttribute(): float
    {
        $rate = (float) ($this->cedular_rate ?? 0);
        return round($this->subtotal * ($rate / 100), 2);
    }

    public function getAdditionalTaxesAmountAttribute(): float
    {
        return round(
            $this->isr_amount
            + $this->retention_iva_amount
                + $this->retention_isr_amount,
            2
        );
    }

    public function getTotalWithIvaAttribute(): float
    {
        return $this->getTotalWithTaxAttribute();
    }

    public function getTotalWithTaxAttribute(): float
    {
        return round(
            $this->subtotal
            + $this->tax_amount
            - $this->isr_amount
            - $this->retention_iva_amount
            - $this->retention_isr_amount
            + $this->cedular_amount,
            2
        );
    }

    /**
     * Recalcula y persiste el campo `amount` a partir de los ítems e impuestos aplicables.
     * Llamar después de crear / editar / eliminar ítems.
     */
    public function recalculateAmount(): bool
    {
        return \DB::transaction(function (): bool {
            $subtotal = (float) $this->items()->sum(\DB::raw('quantity * unit_price'));
            $taxRate = (float) ($this->tax_rate ?? 0);
            $isrRate = (float) ($this->isr_rate ?? 0);
            $retentionIvaRate = (float) ($this->retention_iva_rate ?? 0);
            $retentionIsrRate = (float) ($this->retention_isr_rate ?? 0);
            $cedularRate = (float) ($this->cedular_rate ?? 0);

            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $isrAmount = round($subtotal * ($isrRate / 100), 2);
            $retentionIvaAmount = round($subtotal * ($retentionIvaRate / 100), 2);
            $retentionIsrAmount = round($subtotal * ($retentionIsrRate / 100), 2);
            $cedularAmount = round($subtotal * ($cedularRate / 100), 2);

            $this->update([
                'amount' => round(
                    $subtotal + $taxAmount - $isrAmount - $retentionIvaAmount - $retentionIsrAmount + $cedularAmount,
                    2
                ),
            ]);

            return $this->syncPendingPercentageMilestonePayments();
        });
    }

    private function syncPendingPercentageMilestonePayments(): bool
    {
        $this->unsetRelation('items');
        $this->load('items');

        $percentageMilestones = $this->milestones()
            ->where('value_type', 'porcentaje')
            ->orderBy('id')
            ->get();

        if ($percentageMilestones->isEmpty()) {
            return false;
        }

        $orderTotal = (float) $this->total_with_iva;
        $percentageTotal = (float) $percentageMilestones->sum('value');
        $lastMilestoneId = $percentageMilestones->last()?->id;
        $previousAmounts = 0.0;
        $wasSynchronized = false;

        foreach ($percentageMilestones as $milestone) {
            $isLastPercentageMilestone = abs($percentageTotal - 100) < 0.0001
                && $milestone->id === $lastMilestoneId;
            $targetAmount = $isLastPercentageMilestone
                ? round($orderTotal - $previousAmounts, 2)
                : round($orderTotal * (float) $milestone->value / 100, 2);
            $previousAmounts = round($previousAmounts + $targetAmount, 2);

            $payments = $milestone->payments()
                ->lockForUpdate()
                ->orderBy('id')
                ->get();
            $pendingPayments = $payments->where('status', 'por_autorizar')->values();

            if ($pendingPayments->isEmpty()) {
                continue;
            }

            $lockedAmount = round((float) $payments
                ->where('status', '!=', 'por_autorizar')
                ->sum('amount'), 2);
            $pendingTargetAmount = max(0, round($targetAmount - $lockedAmount, 2));
            $currentPendingAmount = (float) $pendingPayments->sum('amount');
            $remainingAmount = $pendingTargetAmount;

            foreach ($pendingPayments as $index => $payment) {
                $isLastPayment = $index === $pendingPayments->count() - 1;
                $amount = $isLastPayment
                    ? $remainingAmount
                    : ($currentPendingAmount > 0
                        ? round($pendingTargetAmount * ((float) $payment->amount / $currentPendingAmount), 2)
                        : 0.0);

                $remainingAmount = round($remainingAmount - $amount, 2);

                if (abs((float) $payment->amount - $amount) > 0.009) {
                    $payment->update(['amount' => $amount]);
                    $wasSynchronized = true;
                }
            }
        }

        return $wasSynchronized;
    }
}