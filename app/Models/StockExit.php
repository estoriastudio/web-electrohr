<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockExitItem::class);
    }

    public function returnStockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class, 'return_stock_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
