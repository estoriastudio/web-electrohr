<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'mobile_asset_id',
        'folio',
        'inspection_file',
        'maintenance_date',
        'next_maintenance_date',
        'mileage_due',
        'notes',
    ];

    protected $casts = [
        'maintenance_date'      => 'date',
        'next_maintenance_date' => 'date',
        'mileage_due'           => 'integer',
    ];

    public function mobileAsset(): BelongsTo
    {
        return $this->belongsTo(MobileAsset::class);
    }
}
