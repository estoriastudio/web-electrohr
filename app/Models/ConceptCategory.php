<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConceptCategory extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
    ];

    public function subcategories(): HasMany
    {
        return $this->hasMany(ConceptSubcategory::class);
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(Concept::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'concept_category_user');
    }
}
