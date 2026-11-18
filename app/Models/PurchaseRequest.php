<?php

namespace App\Models;

use App\Models\Concerns\HasAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/* Internamente esto se conoce como SOLCOM o Solicitud de Compra */

class PurchaseRequest extends Model
{
    use HasAccessScope, SoftDeletes;

    protected $fillable = [
        'folio',
        'code',
        'material_request_id',
        'project_id',
        'project_work_id',
        'zone',
        'delivery_address',
        'short_description',
        'request_date',
        'need_date',
        'requested_by',
        'assigned_to',
        'status',
        'archived_at',
        'observations',
        'deletion_comment',
    ];

    protected $casts = [
        'request_date' => 'date',
        'need_date' => 'date',
        'archived_at' => 'datetime',
        'observations' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    // En Entrada: sin conceptos o con al menos un concepto sin fincar a una OC vigente.
    public function scopePendingInheritance($query)
    {
        return $query->where(function ($q) {
            $q->whereDoesntHave('items')
                ->orWhereHas('items', fn ($items) => $items->pendingInheritance());
        });
    }

    // Con salida: tiene conceptos y todos están fincados a una OC vigente.
    public function scopeFullyInherited($query)
    {
        return $query->whereHas('items')
            ->whereDoesntHave('items', fn ($items) => $items->pendingInheritance());
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function materialRequests(): BelongsToMany
    {
        return $this->belongsToMany(
            MaterialRequest::class,
            'purchase_request_material_requests',
            'purchase_request_id',
            'material_request_id'
        );
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class, 'project_work_id');
    }

    public function projectWorks(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjectWork::class,
            'purchase_request_project_works',
            'purchase_request_id',
            'project_work_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function changeNotes(): HasMany
    {
        return $this->hasMany(PurchaseRequestChangeNote::class)->orderBy('created_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    // Con la SOLCOM en "Cambios Solicitados" se permite corregir la cantidad de conceptos ya fincados a una OC.
    public function allowsLockedItemQuantityEdit(): bool
    {
        return $this->status === 'changes_requested';
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** Alcances de la matriz: Gerente y Supervisor ven las de sus proyectos; Almacén edita solo las no enviadas a Compras. */
    public function applyAccessScope(Builder $query, string $scope, User $user): void
    {
        match ($scope) {
            'assigned_projects' => $query->whereIn('purchase_requests.project_id', Project::query()->assignedTo($user)->select('projects.id')),
            'unsent_purchase_requests' => $query->whereIn('purchase_requests.status', ['pending', 'changes_requested']),
            default => $this->unsupportedAccessScope($scope),
        };
    }
}
