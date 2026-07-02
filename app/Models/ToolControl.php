<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolControl extends Model
{
    protected $fillable = [
        'tool_id',
        'project_work_id',
        'loan_type',
        'checkout_date',
        'review_date',
        'observations',
        'status',
    ];

    protected $casts = [
        'checkout_date' => 'date',
        'review_date' => 'date',
        'observations' => 'array',
    ];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class, 'project_work_id');
    }
}
