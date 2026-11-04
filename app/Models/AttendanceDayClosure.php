<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDayClosure extends Model
{
    protected $fillable = [
        'project_work_id',
        'date',
        'closed_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }
}
