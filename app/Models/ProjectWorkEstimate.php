<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectWorkEstimate extends Model
{
    public const TYPE_ESTIMACION = 'estimacion';
    public const TYPE_NOTA_CREDITO = 'nota_credito';
    public const TYPE_ANTICIPO = 'anticipo';

    public const STATUS_PENDING = 'pendiente';
    public const STATUS_PAID = 'pagada';

    public const TYPES = [
        self::TYPE_ESTIMACION,
        self::TYPE_NOTA_CREDITO,
        self::TYPE_ANTICIPO,
    ];

    protected $fillable = [
        'project_work_id',
        'created_by',
        'estimate_number',
        'estimate_date',
        'type',
        'estimate_amount',
        'returned_retention_amount',
        'disfp_deduction',
        'apaee_deduction',
        'inc_retention_amount',
        'vat_retention_amount',
        'advance_amortization_amount',
        'advance_amortization_vat_amount',
        'funeral_expense_amount',
        'delay_penalty_amount',
        'invoice_number',
        'invoice_date',
        'invoice_amount',
        'spei_reference',
        'spei_amount',
        'payment_date',
        'status',
        'physical_progress',
        'notes',
    ];

    protected $casts = [
        'estimate_date' => 'date',
        'invoice_date' => 'date',
        'estimate_amount' => 'decimal:2',
        'returned_retention_amount' => 'decimal:2',
        'disfp_deduction' => 'decimal:2',
        'apaee_deduction' => 'decimal:2',
        'inc_retention_amount' => 'decimal:2',
        'vat_retention_amount' => 'decimal:2',
        'advance_amortization_amount' => 'decimal:2',
        'advance_amortization_vat_amount' => 'decimal:2',
        'funeral_expense_amount' => 'decimal:2',
        'delay_penalty_amount' => 'decimal:2',
        'invoice_amount' => 'decimal:2',
        'spei_amount' => 'decimal:2',
        'payment_date' => 'date',
        'physical_progress' => 'decimal:2',
    ];

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getVatAmountAttribute(): float
    {
        return round((float) $this->estimate_amount * 0.16, 2);
    }

    public function getTotalPaymentsAmountAttribute(): float
    {
        return round(
            (float) $this->estimate_amount
            + (float) $this->returned_retention_amount
            + $this->vat_amount,
            2
        );
    }

    public function getTotalDeductionsAmountAttribute(): float
    {
        return round(
            (float) $this->disfp_deduction
            + (float) $this->apaee_deduction
            + (float) $this->inc_retention_amount
            + (float) $this->vat_retention_amount
            + (float) $this->advance_amortization_amount
            + (float) $this->advance_amortization_vat_amount
            + (float) $this->funeral_expense_amount
            + (float) $this->delay_penalty_amount,
            2
        );
    }

    public function getLiquidAmountAttribute(): float
    {
        return round($this->total_payments_amount - $this->total_deductions_amount, 2);
    }
}
