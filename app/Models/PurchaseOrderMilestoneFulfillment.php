<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderMilestoneFulfillment extends Model
{
    // La recepción física no genera registro propio: usa purchase_order_item_deliveries.
    public const TYPE_PHYSICAL = 'physical';
    public const TYPE_ESTIMATION = 'estimation';
    public const TYPE_DOCUMENTARY = 'documentary';
    public const TYPE_ADVANCE = 'advance';

    public const RECORDED_TYPES = [
        self::TYPE_ESTIMATION,
        self::TYPE_DOCUMENTARY,
        self::TYPE_ADVANCE,
    ];

    public const TYPE_LABELS = [
        self::TYPE_PHYSICAL => 'Recepción física',
        self::TYPE_ESTIMATION => 'Estimación o avance',
        self::TYPE_DOCUMENTARY => 'Documental',
        self::TYPE_ADVANCE => 'Anticipo',
    ];

    public const EVIDENCE_HINTS = [
        self::TYPE_ESTIMATION => 'Estimación firmada o visto bueno de la obra',
        self::TYPE_DOCUMENTARY => 'Acta o documento aprobado',
        self::TYPE_ADVANCE => 'Hoja de autorización firmada por Dirección',
    ];

    protected $fillable = [
        'purchase_order_milestone_id',
        'fulfillment_type',
        'evidence_id',
        'comment',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderMilestone::class, 'purchase_order_milestone_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderEvidence::class, 'evidence_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->fulfillment_type] ?? (string) $this->fulfillment_type;
    }
}
