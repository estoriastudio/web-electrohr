<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public function send(array $payload): Notification
    {
        return Notification::create([
            'action_by' => $payload['action_by'],
            'model_action' => $payload['model_action'],
            'model_id' => $payload['model_id'],
            'type' => $payload['type'],
            'data' => $payload['data'],
            'is_hidden' => false,
        ]);
    }

    public function sendToRecipients(array $payload, array $recipientIds): Notification
    {
        $notification = $this->send($payload);
        $recipientIds = collect($recipientIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $notification->recipients()->createMany(
            $recipientIds->map(fn ($userId) => ['user_id' => $userId])->all()
        );

        return $notification;
    }
}
