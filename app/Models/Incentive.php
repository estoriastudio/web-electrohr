<?php

namespace App\Models;

use App\Models\Concerns\HasAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incentive extends Model
{
    use HasAccessScope;

    protected $fillable = [
        'worker_id',
        'category',
        'rate_type',
        'overtime_hours',
        'overtime_hourly_rate',
        'incentive_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'overtime_hours' => 'decimal:2',
        'overtime_hourly_rate' => 'decimal:2',
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

    public function applyAccessScope(Builder $query, string $scope, User $user): void
    {
        match ($scope) {
            'assigned_workers' => $query->whereHas('worker', fn (Builder $workers) => (new Worker)->applyAccessScope($workers, $scope, $user)),
            default => $this->unsupportedAccessScope($scope),
        };
    }
}
