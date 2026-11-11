<?php

namespace App\Models;

use App\Models\Concerns\HasAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectWork extends Model
{
    use HasAccessScope;

    protected $fillable = [
        'project_id', 'name', 'status',
        'supervisor_user_id', 'resident_user_id',
        'contract_number',
        'contract_start_date', 'contract_end_date',
        'contract_value', 'currency',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supervisorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function residentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_user_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'project_work_id');
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(ProjectAgreement::class, 'project_agreement_project_work');
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
            'assigned_works' => $query->whereIn('project_works.id', $user->assignedProjectWorkIds()),
            'assigned_projects' => $query->whereHas('project', fn (Builder $projects) => $projects->assignedTo($user)),
            default => $this->unsupportedAccessScope($scope),
        };
    }
}
