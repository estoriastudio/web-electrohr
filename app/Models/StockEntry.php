<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockEntry extends Model
{
    protected $fillable = [
        'concept_id',
        'tool_id',
        'entry_type',
        'purchase_reference',
        'quantity',
        'received_at',
        'invoice_file_name',
        'invoice_file_path',
        'invoice_disk',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'received_at' => 'date',
    ];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(StockCertificate::class);
    }

    public function returnedExit(): HasOne
    {
        return $this->hasOne(StockExit::class, 'return_stock_entry_id');
    }

}
