<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'ine_expiration_date' => 'date',
        'medical_certificate_expiration_date' => 'date',
    ];

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
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

    public function vacations(): HasMany
    {
        return $this->hasMany(WorkerVacation::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(WorkerAttendance::class);
    }

    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function incentives(): HasMany
    {
        return $this->hasMany(Incentive::class);
    }

    public function pieceworkWeeklyEntries(): HasMany
    {
        return $this->hasMany(PieceworkWeeklyEntry::class);
    }

    public function foremanPieceworkWeeklyEntries(): HasMany
    {
        return $this->hasMany(PieceworkWeeklyEntry::class, 'foreman_worker_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(WorkerGroup::class, 'worker_group_members')
            ->using(WorkerGroupMember::class)
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function currentGroup(): ?WorkerGroup
    {
        return $this->groups()->wherePivotNull('left_at')->first();
    }

    public function currentProjectWork(): ?ProjectWork
    {
        return $this->currentGroup()?->projectWork;
    }
}
