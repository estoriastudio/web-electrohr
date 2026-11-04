<?php

namespace App\Models;

use App\Services\Hr\HrClock;
use App\Services\Hr\HrFieldScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Worker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'nickname',
        'profile_photo_path',
        'rfc',
        'curp',
        'birth_date',
        'hire_date',
        'position_category_id',
        'project_work_id',
        'worker_group_id',
        'registered_by_user_id',
        'field_registered_at',
        'profile_complete_deadline',
        'file_incomplete',
        'weekly_salary',
        'payment_type',
        'is_dc5',
        'status',
        'ine_expiration_date',
        'medical_certificate_expiration_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'nss',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'weekly_salary' => 'decimal:2',
        'is_dc5' => 'boolean',
        'field_registered_at' => 'datetime',
        'profile_complete_deadline' => 'date',
        'file_incomplete' => 'boolean',
        'ine_expiration_date' => 'date',
        'medical_certificate_expiration_date' => 'date',
    ];

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function workerGroup(): BelongsTo
    {
        return $this->belongsTo(WorkerGroup::class);
    }

    public function registeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function positionCategory(): BelongsTo
    {
        return $this->belongsTo(PositionCategory::class);
    }

    public function file(): HasOne
    {
        return $this->hasOne(WorkerFile::class);
    }

    public function dc3s(): HasMany
    {
        return $this->hasMany(\App\Models\WorkerDc3::class)->orderBy('label');
    }

    public function terminations(): HasMany
    {
        return $this->hasMany(WorkerTermination::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(WorkAssignment::class)->current()->latestOfMany('start_date');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(WorkerAttendance::class);
    }

    /**
     * Personas visibles para el campo (HR-011): asignación vigente en una Obra de su alcance
     * o baja de los últimos 7 días que estuvo asignada a una de ellas.
     */
    public function scopeInFieldScope(Builder $query, User $user): Builder
    {
        $workIds = HrFieldScope::works($user)->pluck('id');

        return $query->where(fn (Builder $scope) => $scope
            ->whereHas('assignments', fn (Builder $assignment) => $assignment->current()->whereIn('project_work_id', $workIds))
            ->orWhere(fn (Builder $recent) => $recent->where('status', 'terminated')
                ->whereHas('terminations', fn (Builder $termination) => $termination->whereDate('termination_date', '>=', HrClock::today()->subDays(7)->toDateString()))
                ->whereHas('assignments', fn (Builder $assignment) => $assignment->active()->whereIn('project_work_id', $workIds))));
    }
}
