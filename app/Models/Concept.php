<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concept extends Model
{
    protected $fillable = [
        'code',
        'description',
        'unit',
        'unit_price',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function materialRequestItems(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }
}
