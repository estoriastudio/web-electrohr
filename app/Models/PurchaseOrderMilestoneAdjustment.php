<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderMilestoneAdjustment extends Model
{
    protected $fillable = [
        'milestone_id', 'user_id', 'previous_amount', 'new_amount',
        'previous_value_type', 'previous_value', 'reason', 'partial_milestone_id',
    ];

    protected $casts = [
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'previous_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
