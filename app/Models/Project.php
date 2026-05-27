<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'client_name', 'city', 'state', 'status'];

    public function works(): HasMany
    {
        return $this->hasMany(ProjectWork::class);
    }
}
