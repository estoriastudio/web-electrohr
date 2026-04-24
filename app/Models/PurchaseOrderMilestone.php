<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderMilestone extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'type',
        'value_type',
        'value',
        'covered_amount',
        'invoice_date',
        'due_date',
    ];

    protected $casts = [
        'value'          => 'decimal:2',
        'covered_amount' => 'decimal:2',
        'invoice_date'   => 'date',
        'due_date'       => 'date',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'milestone_id');
    }

    public function getProgressPercentAttribute(): float
    {
        if ((float) $this->value <= 0) {
            return 0.0;
        }

        $percent = ((float) $this->covered_amount / (float) $this->value) * 100;

        return min(100.0, round($percent, 1));
    }

    public function getIsCompleteAttribute(): bool
    {
        return (float) $this->covered_amount >= (float) $this->value && (float) $this->value > 0;
    }
}
