<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Tool extends Model
{
    protected $fillable = [
        'tool_category_id',
        'economic_number',
        'name',
        'description',
        'brand',
        'model',
        'serial_number',
        'status',
        'requires_calibration',
        'photo1',
        'photo2',
        'photo3',
    ];

    protected $casts = [
        'requires_calibration' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(ToolControl::class, 'tool_id')->latest('checkout_date');
    }

    public function calibrations(): HasMany
    {
        return $this->hasMany(ToolCalibration::class)->orderByDesc('calibration_date');
    }

    public function photoUrl(int $slot): ?string
    {
        $field = 'photo' . $slot;

        return $this->$field ? Storage::disk('s3')->url($this->$field) : null;
    }
}
