<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockEntryItem extends Model
{
    protected $fillable = [
        'stock_entry_id',
        'concept_id',
        'line_number',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }
}