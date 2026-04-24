<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PurchaseOrderInvoice extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'file_name',
        'file_path',
        'amount',
        'currency',
        'attached_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'attached_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function milestones(): BelongsToMany
    {
        return $this->belongsToMany(
            PurchaseOrderMilestone::class,
            'invoice_milestone',
            'purchase_order_invoice_id',
            'purchase_order_milestone_id'
        );
    }
}
