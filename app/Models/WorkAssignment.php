<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkAssignment extends Model
{
    protected $fillable = [
        'worker_id',
        'project_id',
        'project_work_id',
        'worker_group_id',
        'start_date',
        'end_date',
        'personnel_request_id',
        'replaced_assignment_id',
        'provisional',
        'approved_by',
        'approved_at',
        'closed_reason',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'provisional' => 'boolean',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function workerGroup(): BelongsTo
    {
        return $this->belongsTo(WorkerGroup::class);
    }

    public function personnelRequest(): BelongsTo
    {
        return $this->belongsTo(PersonnelRequest::class);
    }

    public function replacedAssignment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_assignment_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Asignaciones no anuladas. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /** Asignación vigente: sin fecha de fin y sin anular. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('voided_at')->whereNull('end_date');
    }

    /** Padrón en una fecha: asignaciones vigentes ese día, incluidas las provisionales. */
    public function scopeOnDate(Builder $query, CarbonInterface|string $date): Builder
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $query->whereNull('voided_at')
            ->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $range) => $range->whereNull('end_date')->orWhereDate('end_date', '>=', $date));
    }
}
