<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concept extends Model
{
    protected $fillable = [
        'code',
        'description',
        'unit',
        'unit_price',
        'status',
        'type',
        'concept_category_id',
        'concept_subcategory_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ConceptCategory::class, 'concept_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ConceptSubcategory::class, 'concept_subcategory_id');
    }

    public function materialRequestItems(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
