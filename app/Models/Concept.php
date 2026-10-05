<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Concept extends Model
{
    protected $fillable = [
        'code',
        'description',
        'unit',
        'warehouse_location',
        'unit_price',
        'minimum_stock',
        'maximum_stock',
        'priority',
        'requires_origin_certificate',
        'requires_safety_certificate',
        'status',
        'type',
        'concept_category_id',
        'concept_subcategory_id',
    ];

    protected $attributes = [
        'priority' => 'medium',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'minimum_stock' => 'decimal:3',
        'maximum_stock' => 'decimal:3',
        'requires_origin_certificate' => 'boolean',
        'requires_safety_certificate' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->qualifyColumn('archived_at'));
    }

    // Conceptos que pueden usarse en SOLMAT, SOLCOM, OC y movimientos de inventario.
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->notArchived()->where($query->getModel()->qualifyColumn('status'), 'active');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

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

    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }

    public function stockEntryItems(): HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function stockExits(): HasMany
    {
        return $this->hasMany(StockExit::class);
    }

    public function stockExitItems(): HasMany
    {
        return $this->hasMany(StockExitItem::class);
    }

    public function stockCertificates(): HasManyThrough
    {
        return $this->hasManyThrough(StockCertificate::class, StockEntry::class);
    }
}
