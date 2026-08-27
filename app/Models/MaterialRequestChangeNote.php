<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequestChangeNote extends Model
{
    protected $fillable = [
        'material_request_id',
        'requested_by',
        'text',
        'note_type',
        'payload',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isChangeRequest(): bool
    {
        return $this->note_type === 'change_request';
    }

    public function isCommitmentNotice(): bool
    {
        return $this->note_type === 'commitment_notice';
    }
}
