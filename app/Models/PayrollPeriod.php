<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'week_number',
        'year',
        'start_date',
        'end_date',
        'cutoff_date',
        'status',
        'authorized_by',
        'authorized_at',
        'notes',
    ];

    /** Estatus del periodo; la autorización de Dirección va entre cerrada y pagada (Fase 4, V-02). */
    public const STATUS_LABELS = [
        'open' => 'Abierto',
        'closed' => 'Cerrado, por autorizar',
        'authorized' => 'Autorizado por Dirección',
        'paid' => 'Pagado',
    ];

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    protected $casts = [
        'authorized_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'cutoff_date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
