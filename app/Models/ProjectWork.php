<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectWork extends Model
{
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

    public function isAttendanceResponsible(User $user): bool
    {
        return in_array($user->id, [$this->supervisor_user_id, $this->resident_user_id], true);
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
}
