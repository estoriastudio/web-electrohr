<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderEvidence extends Model
{
    protected $table = 'purchase_order_evidences';

    protected $fillable = [
        'purchase_order_id',
        'purchase_order_milestone_id',
        'purchase_order_invoice_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'mime_type',
        'source',
        'description',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderMilestone::class, 'purchase_order_milestone_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderInvoice::class, 'purchase_order_invoice_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
