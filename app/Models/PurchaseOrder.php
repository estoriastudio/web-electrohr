<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PurchaseOrder extends Model
{
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
        'status',
        'recurrence_type',
        'recurrence_frequency',
        'recurrence_start_date',
        'recurrence_end_date',
        'observations',
        'elaborated_by',
        'supplier_signatory',
        'authorized_signatory',
    ];

    protected $casts = [
        'amount'                => 'decimal:2',
        'recurrence_start_date' => 'date',
        'recurrence_end_date'   => 'date',
        'observations'          => 'array',
    ];

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
        return round($this->subtotal * 0.16, 2);
    }

    public function getTotalWithIvaAttribute(): float
    {
        return round($this->subtotal * 1.16, 2);
    }

    /**
     * Recalcula y persiste el campo `amount` a partir de los ítems (subtotal + IVA 16%).
     * Llamar después de crear / editar / eliminar ítems.
     */
    public function recalculateAmount(): void
    {
        $subtotal = (float) $this->items()->sum(\DB::raw('quantity * unit_price'));

        if ($subtotal > 0) {
            $this->update(['amount' => round($subtotal * 1.16, 2)]);
        }
    }
}