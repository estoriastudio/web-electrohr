<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MaterialRequestItemProjectWork;

class MaterialRequestItem extends Model
{
    protected $fillable = [
        'material_request_id',
        'concept_id',
        'code',
        'description',
        'unit',
        'quantity',
        'file_path',
    ];

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function workQuantities(): HasMany
    {
        return $this->hasMany(MaterialRequestItemProjectWork::class);
    }

    public function getTotalQuantityAttribute(): float
    {
        if ($this->relationLoaded('workQuantities') && $this->workQuantities->isNotEmpty()) {
            return (float) $this->workQuantities->sum('quantity');
        }

        return (float) $this->quantity;
    }
}
