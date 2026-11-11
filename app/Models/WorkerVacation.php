<?php

namespace App\Models;

use App\Models\Concerns\HasAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerVacation extends Model
{
    use HasAccessScope;

    protected $fillable = [
        'worker_id',
        'start_date',
        'end_date',
        'total_days',
        'request_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'request_date' => 'date',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function applyAccessScope(Builder $query, string $scope, User $user): void
    {
        match ($scope) {
            'assigned_workers' => $query->whereHas('worker', fn (Builder $workers) => (new Worker)->applyAccessScope($workers, $scope, $user)),
            default => $this->unsupportedAccessScope($scope),
        };
    }
}
