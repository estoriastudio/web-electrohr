<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incentive extends Model
{
    protected $fillable = [
        'worker_id',
        'category',
        'rate_type',
        'incentive_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'incentive_date' => 'date',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function extraPayments(): HasMany
    {
        return $this->hasMany(PayrollLineExtraPayment::class);
    }
}