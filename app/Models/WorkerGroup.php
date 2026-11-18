<?php

namespace App\Models;

use App\Models\Concerns\HasAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkerGroup extends Model
{
    use HasAccessScope;

    protected $fillable = [
        'name',
        'project_work_id',
        'status',
        'notes',
    ];

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'worker_group_members')
            ->using(WorkerGroupMember::class)
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivotNull('left_at');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(WorkerAttendance::class);
    }

    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function pieceworkWeeklyEntries(): HasMany
    {
        return $this->hasMany(PieceworkWeeklyEntry::class);
    }

    public function applyAccessScope(Builder $query, string $scope, User $user): void
    {
        match ($scope) {
            'assigned_works' => $query->whereIn('worker_groups.project_work_id', $user->assignedProjectWorkIds()),
            default => $this->unsupportedAccessScope($scope),
        };
    }
}
