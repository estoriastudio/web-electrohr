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
        'sent_to_warehouse_at',
        'observations',
        'deletion_comment',
        'archived_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'need_date'    => 'date',
        'sent_to_warehouse_at' => 'datetime',
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

    // Las OCs vinculadas llegan por SOLCOM directas (material_request_id) o por el pivote de SOLCOMs ligadas.
    private function whereHasLinkedOrders($query, ?\Closure $constraint = null, bool $negate = false)
    {
        // Con $negate = true ninguna de las dos rutas debe tener OCs (AND); en caso contrario basta una (OR).
        return $negate
            ? $query->whereDoesntHave('purchaseRequests.purchaseOrders', $constraint)
                ->whereDoesntHave('linkedPurchaseRequests.purchaseOrders', $constraint)
            : $query->whereHas('purchaseRequests.purchaseOrders', $constraint)
                ->orWhereHas('linkedPurchaseRequests.purchaseOrders', $constraint);
    }

    // Completada: tiene al menos una OC vinculada y todas están cerradas.
    public function scopeCompletedTray($query)
    {
        return $query->where(function ($q) {
            $q->where(fn ($linked) => $this->whereHasLinkedOrders($linked))
                ->where(fn ($open) => $this->whereHasLinkedOrders($open, fn ($oc) => $oc->whereNull('closed_at'), true));
        });
    }

    // Abierta: sin OCs vinculadas o con al menos una OC sin cerrar.
    public function scopeOpenTray($query)
    {
        return $query->where(function ($q) {
            $q->where(fn ($none) => $this->whereHasLinkedOrders($none, null, true))
                ->orWhere(fn ($open) => $this->whereHasLinkedOrders($open, fn ($oc) => $oc->whereNull('closed_at')));
        });
    }

    public function getLinkedPurchaseOrdersAttribute()
    {
        return $this->purchaseRequests
            ->merge($this->linkedPurchaseRequests)
            ->flatMap->purchaseOrders
            ->unique('id')
            ->values();
    }

    public function getIsCompletedAttribute(): bool
    {
        $orders = $this->linked_purchase_orders;

        return $orders->isNotEmpty() && $orders->every(fn ($order) => $order->closed_at !== null);
    }

    public function canBeSentToWarehouseBy(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'Solmat'])
            || ($this->project !== null && $this->project->isManagedBy($user));
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

    public function changeNotes(): HasMany
    {
        return $this->hasMany(MaterialRequestChangeNote::class)->orderBy('created_at');
    }

    public function getTotalCommittedQuantityAttribute(): float
    {
        return (float) $this->items
            ->flatMap(fn (MaterialRequestItem $item) => $item->workQuantities)
            ->sum('committed_quantity');
    }

    public function getTotalRequestedQuantityAttribute(): float
    {
        return (float) $this->items
            ->sum(fn (MaterialRequestItem $item) => $item->total_quantity);
    }

    public function getCommittedPercentAttribute(): float
    {
        if ($this->total_requested_quantity <= 0) {
            return 0;
        }

        return ($this->total_committed_quantity / $this->total_requested_quantity) * 100;
    }

    public function hasAvailableQuantityForProjectWorks(): bool
    {
        $projectWorkIds = $this->projectWorks
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $this->items->contains(function (MaterialRequestItem $item) use ($projectWorkIds) {
            if ($item->relationLoaded('workQuantities') && $item->workQuantities->isNotEmpty()) {
                return (float) $item->workQuantities
                    ->filter(fn (MaterialRequestItemProjectWork $workQuantity) =>
                        $projectWorkIds->contains((int) $workQuantity->project_work_id)
                    )
                    ->sum(fn (MaterialRequestItemProjectWork $workQuantity) =>
                        max(0, (float) $workQuantity->quantity - (float) $workQuantity->committed_quantity)
                    ) > 0;
            }

            return (float) $item->quantity > 0;
        });
    }
}
