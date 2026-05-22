<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierLocation extends Model
{
    protected $fillable = [
        'supplier_id',
        'name',
        'street',
        'colony',
        'postal_code',
        'state',
        'city',
        'bank_name',
        'bank_account',
        'bank_clabe',
        'currency',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
