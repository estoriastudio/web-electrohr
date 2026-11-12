<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Confirmación en obra de una salida definitiva de Almacén (Fase 4, V-06). */
class SiteDeliveryConfirmation extends Model
{
    protected $fillable = ['stock_exit_id', 'status', 'notes', 'confirmed_by', 'confirmed_at'];

    protected $casts = ['confirmed_at' => 'datetime'];

    public function stockExit(): BelongsTo
    {
        return $this->belongsTo(StockExit::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SiteDeliveryConfirmationItem::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function hasDiscrepancy(): bool
    {
        return $this->status === 'discrepancy';
    }
}
