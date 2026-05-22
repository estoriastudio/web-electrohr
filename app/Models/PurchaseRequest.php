<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* Internamente esto se conoce como SOLCOM o Solicitud de Compra */

class PurchaseRequest extends Model
{
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
        'status',
        'observations',
    ];

    protected $casts = [
        'request_date' => 'date',
        'need_date'    => 'date',
        'observations' => 'array',
    ];

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

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }
}
