<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkerGroup extends Model
{
    protected $fillable = [
        'name',
        'status',
        'notes',
    ];

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }
}
