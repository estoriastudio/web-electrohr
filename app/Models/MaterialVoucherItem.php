<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialVoucherItem extends Model
{
    protected $fillable = [
        'material_voucher_id',
        'quantity',
        'unit',
        'description',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function materialVoucher(): BelongsTo
    {
        return $this->belongsTo(MaterialVoucher::class);
    }
}
