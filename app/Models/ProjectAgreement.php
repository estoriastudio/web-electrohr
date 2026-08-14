<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectAgreement extends Model
{
    protected $fillable = [
        'project_id',
        'contracted_amount',
        'current_amount',
        'contracted_end_date',
        'contracted_term_days',
        'current_end_date',
        'agreement_number',
        'new_amount',
        'new_end_date',
        'appointments_file_path',
        'appointments_file_name',
        'appointments_file_mime',
    ];

    protected $casts = [
        'contracted_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'contracted_end_date' => 'date',
        'contracted_term_days' => 'integer',
        'current_end_date' => 'date',
        'new_amount' => 'decimal:2',
        'new_end_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function works(): BelongsToMany
    {
        return $this->belongsToMany(ProjectWork::class, 'project_agreement_project_work');
    }
}
