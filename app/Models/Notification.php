<?php

namespace App\Models;

use App\Models\NotificationRecipient as NotificationRecipientModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    protected $fillable = [
        'action_by',
        'model_action',
        'model_id',
        'type',
        'data',
        'read_at',
        'is_hidden',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipientModel::class);
    }
}
