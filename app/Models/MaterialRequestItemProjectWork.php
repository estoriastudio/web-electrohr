<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequestItemProjectWork extends Model
{
    public $timestamps = false;

    protected $table = 'material_request_item_project_works';

    protected $fillable = [
        'material_request_item_id',
        'project_work_id',
        'quantity',
        'is_committed',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'is_committed' => 'boolean',
    ];

    public function materialRequestItem(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestItem::class);
    }

    public function projectWork(): BelongsTo
    {
        return $this->belongsTo(ProjectWork::class);
    }
}