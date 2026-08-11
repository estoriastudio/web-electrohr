<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
