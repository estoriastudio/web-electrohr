<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'milestone_id',
        'folio',
        'amount',
        'payment_date',
        'invoice_date',
        'status',
        'reference_number',
        'spei_receipt_path',
        'spei_receipt_name',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
        'invoice_date' => 'date',
    ];

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderMilestone::class, 'milestone_id');
    }

    public function invoiceAllocations(): HasMany
    {
        return $this->hasMany(InvoicePaymentAllocation::class);
    }
}
