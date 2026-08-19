<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceworkDailyAmount extends Model
{
    protected $fillable = [
        'piecework_weekly_entry_id',
        'date',
        'day_of_week',
        'amount',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function pieceworkWeeklyEntry(): BelongsTo
    {
        return $this->belongsTo(PieceworkWeeklyEntry::class);
    }
}