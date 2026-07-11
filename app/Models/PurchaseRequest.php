<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PurchaseOrder;

/* Internamente esto se conoce como SOLCOM o Solicitud de Compra */

class PurchaseRequest extends Model
{
    use SoftDeletes;

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
        'need_date'    => 'date',
        'archived_at'  => 'datetime',
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

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class, 'project_work_id');
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

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
