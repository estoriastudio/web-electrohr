<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const COMMITTED_STATUSES = ['por_autorizar', 'autorizado', 'pagado', 'pospuesto'];

    public const STATUS_SUSTITUIDO = 'sustituido';

    /** Pagos que aún no se autorizan: se recalculan cuando cambia el saldo del hito. */
    public const PENDING_STATUSES = ['por_autorizar', 'pospuesto'];

    public const ORIGIN_INITIAL = 'initial';

    public const ORIGIN_REST = 'rest';

    public const ORIGIN_CREDIT = 'credit';

    public const ORIGIN_REPLACEMENT = 'replacement';

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
        'original_amount',
        'paid_amount',
        'applied_amount',
        'origin',
        'source_payment_id',
        'replaced_by_payment_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
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

    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_payment_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_payment_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(PurchaseOrderMilestoneCredit::class, 'source_payment_id');
    }

    /** Importe del SPEI; en pagos anteriores sin `paid_amount` es `amount`. */
    public function getPaidAmountValueAttribute(): float
    {
        return (float) ($this->paid_amount ?? $this->amount);
    }

    /** Importe que se aplicó al hito del pago; en pagos anteriores sin `applied_amount` es `amount`. */
    public function getAppliedAmountValueAttribute(): float
    {
        return (float) ($this->applied_amount ?? $this->amount);
    }

    public function getIsReplacedAttribute(): bool
    {
        return $this->status === self::STATUS_SUSTITUIDO;
    }

    /** SQL para sumar el importe del SPEI de pagos `pagado` (usa `amount` si `paid_amount` está vacío). */
    public const PAID_AMOUNT_SQL = 'COALESCE(payments.paid_amount, payments.amount)';
}
