<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDeliveryConfirmationItem extends Model
{
    protected $fillable = ['site_delivery_confirmation_id', 'stock_exit_item_id', 'concept_id', 'sent_quantity', 'received_quantity'];

    protected $casts = ['sent_quantity' => 'decimal:3', 'received_quantity' => 'decimal:3'];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }
}
