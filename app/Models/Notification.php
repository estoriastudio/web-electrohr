<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'action_by',
        'model_action',
        'model_id',
        'type',
        'data',
        'read_at',
        'is_hidden'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'action_by');
    }
}
