<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolCalibration extends Model
{
    protected $fillable = [
        'tool_id',
        'folio',
        'file_path',
        'calibration_date',
        'expiry_date',
        'observations',
    ];

    protected $casts = [
        'calibration_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
