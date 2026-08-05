<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderInvoice extends Model
{
    public const STATUS_EN_PROCESO = 'en_proceso';
    public const STATUS_ACEPTADA = 'aceptada';
    public const STATUS_RECHAZADA = 'rechazada';

    public const STATUSES = [
        self::STATUS_EN_PROCESO,
        self::STATUS_ACEPTADA,
        self::STATUS_RECHAZADA,
    ];

    protected $fillable = [
        'purchase_order_id',
        'folio',
        'status',
        'issue_date',
        'file_name',
        'file_path',
        'xml_file_name',
        'xml_file_path',
        'evidence_file_name',
        'evidence_file_path',
        'amount',
        'currency',
        'due_date',
        'credit_note_file_name',
        'credit_note_file_path',
        'credit_note_xml_file_name',
        'credit_note_xml_file_path',
        'credit_note_amount',
        'net_scope',
        'attached_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'credit_note_amount' => 'decimal:2',
        'net_scope'   => 'decimal:2',
        'due_date'    => 'date',
        'issue_date'  => 'date',
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

    public function evidences(): HasMany
    {
        return $this->hasMany(PurchaseOrderEvidence::class, 'purchase_order_invoice_id');
    }
}
