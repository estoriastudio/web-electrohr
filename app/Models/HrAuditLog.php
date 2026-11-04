<?php

namespace App\Models;

use App\Services\Hr\HrPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'action',
        'entity',
        'entity_id',
        'worker_id',
        'before',
        'after',
        'reason',
        'sensitive',
    ];

    protected $casts = [
        'sensitive' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->when(
            ! HrPermissions::can('seeAmounts', $user),
            fn (Builder $query) => $query->where('sensitive', false)
        );
    }
}
