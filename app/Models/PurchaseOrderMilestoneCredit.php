<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Abono de un pago de más a un hito posterior al del pago de origen. */
class PurchaseOrderMilestoneCredit extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'milestone_id',
        'source_payment_id',
        'amount',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderMilestone::class, 'milestone_id');
    }

    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
