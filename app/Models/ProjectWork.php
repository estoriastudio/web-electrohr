<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectWork extends Model
{
    protected $fillable = [
        'project_id', 'name', 'status',
        'supervisor', 'resident',
        'contract_number',
        'contract_start_date', 'contract_end_date',
        'contract_value', 'currency',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'project_work_id');
    }
}
