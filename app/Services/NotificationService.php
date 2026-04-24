<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{   
    public function send(array $payload): void
    {
        /* LOG */
        Notification::create([
            'action_by'    => $payload['action_by'],
            'model_action' => $payload['model_action'],
            'model_id'     => $payload['model_id'],
            'type'         => $payload['type'],
            'data'         => $payload['data'],
            'is_hidden'    => false,
        ]);
    }
}