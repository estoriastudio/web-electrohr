<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCertificate extends Model
{
    protected $fillable = [
        'stock_entry_id',
        'concept_id',
        'certificate_type',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class, 'stock_entry_id');
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

}
