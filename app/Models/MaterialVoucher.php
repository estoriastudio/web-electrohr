<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialVoucher extends Model
{
    protected $fillable = [
        'folio',
        'folio_prefix',
        'folio_number',
        'supplier_id',
        'project_id',
        'project_work_id',
        'voucher_date',
        'status',
        'authorized_by',
        'authorized_at',
        'authorized_signature_name',
        'authorized_signature',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'voucher_date'   => 'date',
        'authorized_at'  => 'datetime',
        'observations'   => 'array',
        'folio_number'   => 'integer',
    ];

    public const STATUSES = ['emitido', 'autorizado', 'completado', 'facturado', 'pagado'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialVoucherItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
