<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEstimateAllocation extends Model
{
    protected $fillable = [
        'project_estimate_id',
        'project_work_id',
        'estimate_amount',
    ];

    protected $casts = [
        'estimate_amount' => 'decimal:2',
    ];

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(ProjectEstimate::class, 'project_estimate_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class, 'project_work_id');
    }
}