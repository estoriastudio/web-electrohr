<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'parent_id',
        'folio',
        'purchase_request_id',
        'type',
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
        'status',
        'recurrence_type',
        'recurrence_frequency',
        'recurrence_start_date',
        'recurrence_end_date',
        'observations',
        'elaborated_by',
        'attorney_name',
        'supplier_signatory',
        'authorized_signatory',
        'archived_at',
    ];

    protected $casts = [
        'amount'                => 'decimal:2',
        'tax_rate'              => 'decimal:2',
        'isr_rate'              => 'decimal:2',
        'retention_iva_rate'    => 'decimal:2',
        'retention_isr_rate'    => 'decimal:2',
        'recurrence_start_date' => 'date',
        'recurrence_end_date'   => 'date',
        'observations'          => 'array',
        'archived_at'           => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function projectRelation(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workRelation(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class, 'project_work_id');
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

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
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
        return round($this->subtotal + $this->tax_amount + $this->additional_taxes_amount, 2);
    }

    /**
     * Recalcula y persiste el campo `amount` a partir de los ítems (subtotal + IVA 16%).
     * Llamar después de crear / editar / eliminar ítems.
     */
    public function recalculateAmount(): void
    {
        $subtotal = (float) $this->items()->sum(\DB::raw('quantity * unit_price'));
        $taxRate = (float) ($this->tax_rate ?? 0);
        $isrRate = (float) ($this->isr_rate ?? 0);
        $retentionIvaRate = (float) ($this->retention_iva_rate ?? 0);
        $retentionIsrRate = (float) ($this->retention_isr_rate ?? 0);

        $taxAmount = $subtotal * ($taxRate / 100);
        $isrAmount = $subtotal * ($isrRate / 100);
        $retentionIvaAmount = $subtotal * ($retentionIvaRate / 100);
        $retentionIsrAmount = $subtotal * ($retentionIsrRate / 100);

        $this->update([
            'amount' => round($subtotal + $taxAmount + $isrAmount + $retentionIvaAmount + $retentionIsrAmount, 2),
        ]);
    }
}