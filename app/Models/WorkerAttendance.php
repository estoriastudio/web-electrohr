<?php

namespace App\Models;

use App\Models\Concerns\HasAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerAttendance extends Model
{
    use HasAccessScope;

    protected $fillable = [
        'worker_id',
        'worker_group_id',
        'payroll_line_id',
        'date',
        'week_number',
        'year',
        'role',
        'attended',
        'absence_reason',
        'absence_document_path',
        'overtime_hours',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'attended' => 'boolean',
        'overtime_hours' => 'decimal:2',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function workerGroup(): BelongsTo
    {
        return $this->belongsTo(WorkerGroup::class);
    }

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class);
    }

    public function applyAccessScope(Builder $query, string $scope, User $user): void
    {
        match ($scope) {
            'assigned_works' => $query->whereHas('workerGroup', fn (Builder $groups) => $groups
                ->whereIn('worker_groups.project_work_id', $user->assignedProjectWorkIds())),
            default => $this->unsupportedAccessScope($scope),
        };
    }
}
