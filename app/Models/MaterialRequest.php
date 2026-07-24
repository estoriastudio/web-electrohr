<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/* Internamente esto se conoce como SOLMAT o Solicitud de Materiales */

class MaterialRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'folio',
        'code',
        'project_id',
        'zone',
        'delivery_address',
        'location_type',
        'request_date',
        'need_date',
        'supply_category',
        'concept_category_id',
        'requested_by',
        'status',
        'observations',
        'deletion_comment',
        'archived_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'need_date'    => 'date',
        'observations' => 'array',
        'archived_at'  => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function conceptCategory(): BelongsTo
    {
        return $this->belongsTo(ConceptCategory::class, 'concept_category_id');
    }

    public function projectWorks(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjectWork::class,
            'material_request_project_works',
            'material_request_id',
            'project_work_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function linkedPurchaseRequests(): BelongsToMany
    {
        return $this->belongsToMany(
            PurchaseRequest::class,
            'purchase_request_material_requests',
            'material_request_id',
            'purchase_request_id'
        );
    }
}
