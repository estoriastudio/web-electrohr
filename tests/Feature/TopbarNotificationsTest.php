<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_topbar_separates_global_and_recipient_notifications(): void
    {
        $recipient = User::factory()->create();
        $recipient->assignRole('Solmat');
        $otherUser = User::factory()->create();
        $otherUser->assignRole('Solmat');

        $globalNotification = $this->notification($recipient, 'Notificacion global');
        $recipientNotification = $this->notification($recipient, 'Notificacion personal');
        $otherNotification = $this->notification($otherUser, 'Notificacion de otro usuario');

        NotificationRecipient::create([
            'notification_id' => $recipientNotification->id,
            'user_id' => $recipient->id,
        ]);
        NotificationRecipient::create([
            'notification_id' => $otherNotification->id,
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($recipient);

        $topbar = view('layouts.partials._topbar')->render();

        $this->assertStringContainsString('Notificaciones globales', $topbar);
        $this->assertStringContainsString($globalNotification->data, $topbar);
    $this->assertMatchesRegularExpression('/id="global-notif-badge"[^>]*>\s*1\s*/', $topbar);
        $this->assertStringContainsString('Notificaciones personales', $topbar);
        $this->assertStringContainsString($recipientNotification->data, $topbar);
        $this->assertStringNotContainsString($otherNotification->data, $topbar);
    }

    private function notification(User $user, string $data): Notification
    {
        return Notification::create([
            'action_by' => $user->id,
            'model_action' => 'create',
            'model_id' => 1,
            'type' => 'test',
            'data' => $data,
            'is_hidden' => false,
        ]);
    }
}