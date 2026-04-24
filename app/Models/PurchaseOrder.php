<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'type',
        'supplier_id',
        'project',
        'site',
        'currency',
        'amount',
        'status',
        'recurrence_type',
        'recurrence_frequency',
        'recurrence_start_date',
        'recurrence_end_date',
    ];

    protected $casts = [
        'amount'                => 'decimal:2',
        'recurrence_start_date' => 'date',
        'recurrence_end_date'   => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PurchaseOrderMilestone::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, PurchaseOrderMilestone::class, 'purchase_order_id', 'milestone_id');
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
}
