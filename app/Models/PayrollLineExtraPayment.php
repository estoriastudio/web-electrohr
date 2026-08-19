<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLineExtraPayment extends Model
{
    protected $fillable = [
        'payroll_line_id',
        'incentive_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class);
    }

    public function incentive(): BelongsTo
    {
        return $this->belongsTo(Incentive::class);
    }
}