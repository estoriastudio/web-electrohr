<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierLocation extends Model
{
    protected $fillable = [
        'supplier_id',
        'name',
        'bank_name',
        'bank_account',
        'bank_clabe',
        'currency',
        'account_statement_path',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
