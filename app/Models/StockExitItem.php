<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockExitItem extends Model
{
    protected $fillable = [
        'stock_exit_id',
        'concept_id',
        'line_number',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function stockExit(): BelongsTo
    {
        return $this->belongsTo(StockExit::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }
}