<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PositionCategory extends Model
{
    protected $fillable = [
        'name',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function terminations(): HasMany
    {
        return $this->hasMany(WorkerTermination::class);
    }
}
