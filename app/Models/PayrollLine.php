<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollLine extends Model
{
    protected $fillable = [
        'payroll_period_id', 'worker_id', 'project_work_id', 'worker_group_id', 'position_category_id',
        'base_salary', 'meal_days', 'meal_amount_per_day', 'meal_total_amount', 'sunday_worked', 'sunday_amount',
        'lost_material_amount', 'loan_amount', 'infonavit_amount', 'savings_fund_amount', 'absence_days',
        'absence_amount', 'extras_amount', 'incentive_amount', 'subtotal_amount', 'fiscal_amount',
        'complement_amount', 'total_amount',
    ];

    protected $casts = [
        'sunday_worked' => 'boolean',
        'base_salary' => 'decimal:2',
        'meal_amount_per_day' => 'decimal:2',
        'meal_total_amount' => 'decimal:2',
        'sunday_amount' => 'decimal:2',
        'lost_material_amount' => 'decimal:2',
        'loan_amount' => 'decimal:2',
        'infonavit_amount' => 'decimal:2',
        'savings_fund_amount' => 'decimal:2',
        'absence_amount' => 'decimal:2',
        'extras_amount' => 'decimal:2',
        'incentive_amount' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'fiscal_amount' => 'decimal:2',
        'complement_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }

    public function workerGroup(): BelongsTo
    {
        return $this->belongsTo(WorkerGroup::class);
    }

    public function positionCategory(): BelongsTo
    {
        return $this->belongsTo(PositionCategory::class);
    }

    public function extraPayments(): HasMany
    {
        return $this->hasMany(PayrollLineExtraPayment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(WorkerAttendance::class);
    }
}