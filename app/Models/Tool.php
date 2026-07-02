<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function controls(): HasMany
    {
        return $this->hasMany(ToolControl::class, 'tool_id')->latest('checkout_date');
    }
}
