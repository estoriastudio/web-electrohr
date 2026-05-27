<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileAsset extends Model
{
    protected $fillable = [
        'name',
        'folio',
        'brand',
        'asset_function',
        'type',
        'plates',
        'status',
    ];

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)
                    ->where('type', 'mantenimiento');
    }
}
