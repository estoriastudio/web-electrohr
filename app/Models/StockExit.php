<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class StockExit extends Model
{
    protected $fillable = [
        'concept_id',
        'tool_id',
        'exit_type',
        'voucher_number',
        'recipient_worker_id',
        'recipient_name',
        'project_id',
        'project_work_id',
        'quantity',
        'exited_at',
        'expected_return_at',
        'status',
        'return_stock_entry_id',
        'overdue_notified_at',
        'is_adjustment',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'exited_at' => 'date',
        'expected_return_at' => 'date',
        'overdue_notified_at' => 'datetime',
        'is_adjustment' => 'boolean',
    ];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function recipientWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'recipient_worker_id');
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
        return $this->hasMany(StockExitItem::class);
    }

    public function returnStockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class, 'return_stock_entry_id');
    }

    /** Confirmación de recepción en obra (Fase 4). */
    public function siteDeliveryConfirmation(): HasOne
    {
        return $this->hasOne(SiteDeliveryConfirmation::class);
    }

    /** Renglones enviados: los de la salida, o la línea única de salidas anteriores a los renglones. */
    public function sentLines(): Collection
    {
        if ($this->items->isNotEmpty()) {
            return $this->items->map(fn ($item) => [
                'stock_exit_item_id' => $item->id,
                'concept_id' => $item->concept_id,
                'concept' => $item->concept,
                'quantity' => (float) $item->quantity,
            ]);
        }

        return collect([[
            'stock_exit_item_id' => null,
            'concept_id' => $this->concept_id,
            'concept' => $this->concept,
            'quantity' => (float) $this->quantity,
        ]]);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
