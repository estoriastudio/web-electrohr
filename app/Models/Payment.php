<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const COMMITTED_STATUSES = ['por_autorizar', 'autorizado', 'pagado', 'pospuesto'];

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
        'checklist_verified_at',
        'regularization_invoice_id',
        'regularized_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
        'invoice_date' => 'date',
        'checklist_verified_at' => 'datetime',
        'regularized_at' => 'datetime',
    ];

    public function regularizationInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderInvoice::class, 'regularization_invoice_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderMilestone::class, 'milestone_id');
    }
}
