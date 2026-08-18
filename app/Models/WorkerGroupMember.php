<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkerGroupMember extends Pivot
{
    protected $table = 'worker_group_members';

    public $incrementing = true;

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
    ];
}