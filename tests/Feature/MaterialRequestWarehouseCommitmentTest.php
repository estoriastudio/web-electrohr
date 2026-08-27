<?php

namespace Tests\Feature;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\MaterialRequestItemProjectWork;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialRequestWarehouseCommitmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_suministros_can_commit_items_and_notifies_the_solmat_team(): void
    {
        [$materialRequest, $item, $work] = $this->materialRequest('sent_to_warehouse');
        $warehouseUser = $this->userWithRole('suministros');
        $requester = $this->userWithRole('Solmat');
        $otherSolmatUser = $this->userWithRole('Solmat');
        $materialRequest->update(['requested_by' => $requester->id]);

        $this->actingAs($warehouseUser)
            ->post(route('warehouse.solmat_pile.commitments.update', $materialRequest), [
                'commitments' => [
                    $item->id => [$work->id => 1],
                ],
            ])
            ->assertRedirect(route('warehouse.solmat_pile'));

        $this->assertDatabaseHas('material_request_item_project_works', [
            'material_request_item_id' => $item->id,
            'project_work_id' => $work->id,
            'is_committed' => true,
            'committed_by' => $warehouseUser->id,
        ]);
        $this->assertNotNull(MaterialRequestItemProjectWork::query()
            ->where('material_request_item_id', $item->id)
            ->where('project_work_id', $work->id)
            ->value('committed_at'));
        $this->assertSame('sent_to_warehouse', $materialRequest->fresh()->status);
        $this->assertDatabaseHas('material_request_change_notes', [
            'material_request_id' => $materialRequest->id,
            'requested_by' => $warehouseUser->id,
            'note_type' => 'commitment_notice',
        ]);

        $notification = Notification::query()
            ->where('type', 'material_request_commitment')
            ->firstOrFail();

        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $requester->id,
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $otherSolmatUser->id,
        ]);
        $this->assertFalse($materialRequest->fresh()
            ->load(['projectWorks', 'items.workQuantities'])
            ->hasAvailableQuantityForProjectWorks());
    }

    public function test_solmat_cannot_access_the_warehouse_commitment_endpoint(): void
    {
        [$materialRequest, $item, $work] = $this->materialRequest('sent_to_warehouse');
        $solmatUser = $this->userWithRole('Solmat');

        $this->actingAs($solmatUser)
            ->post(route('warehouse.solmat_pile.commitments.update', $materialRequest), [
                'commitments' => [
                    $item->id => [$work->id => 1],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('material_request_item_project_works', [
            'material_request_item_id' => $item->id,
            'project_work_id' => $work->id,
            'is_committed' => false,
        ]);
    }

    public function test_suministros_can_commit_a_legacy_item_with_one_project_work(): void
    {
        [$materialRequest, $item, $work] = $this->materialRequest('sent_to_warehouse');
        $item->workQuantities()->delete();
        $warehouseUser = $this->userWithRole('suministros');

        $this->actingAs($warehouseUser)
            ->post(route('warehouse.solmat_pile.commitments.update', $materialRequest), [
                'commitments' => [
                    $item->id => [$work->id => 1],
                ],
            ])
            ->assertRedirect(route('warehouse.solmat_pile'));

        $this->assertDatabaseHas('material_request_item_project_works', [
            'material_request_item_id' => $item->id,
            'project_work_id' => $work->id,
            'quantity' => 4,
            'is_committed' => true,
            'committed_by' => $warehouseUser->id,
        ]);
    }

    public function test_solmat_cannot_change_commitment_through_item_editing(): void
    {
        [$materialRequest, $item, $work] = $this->materialRequest('pending');
        $solmatUser = $this->userWithRole('Solmat');

        $this->actingAs($solmatUser)
            ->patch(route('material_requests.items.update', [$materialRequest, $item]), [
                'work_quantities' => [[
                    'work_id' => $work->id,
                    'quantity' => '4.00',
                    'is_committed' => 1,
                ]],
            ])
            ->assertRedirect(route('material_requests.show', $materialRequest));

        $this->assertDatabaseHas('material_request_item_project_works', [
            'material_request_item_id' => $item->id,
            'project_work_id' => $work->id,
            'is_committed' => false,
        ]);
    }

    public function test_marking_notifications_read_only_affects_the_current_recipient(): void
    {
        $firstUser = $this->userWithRole('Solmat');
        $secondUser = $this->userWithRole('Solmat');
        $notification = Notification::create([
            'action_by' => $firstUser->id,
            'model_action' => 'update',
            'model_id' => 1,
            'type' => 'material_request_commitment',
            'data' => 'Aviso de prueba.',
            'is_hidden' => false,
        ]);
        $firstRecipient = NotificationRecipient::create([
            'notification_id' => $notification->id,
            'user_id' => $firstUser->id,
        ]);
        $secondRecipient = NotificationRecipient::create([
            'notification_id' => $notification->id,
            'user_id' => $secondUser->id,
        ]);

        $this->actingAs($firstUser)
            ->post(route('notifications.markAllRead'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($firstRecipient->fresh()->read_at);
        $this->assertNull($secondRecipient->fresh()->read_at);
    }

    private function materialRequest(string $status): array
    {
        $project = Project::create([
            'name' => 'Proyecto de prueba',
            'client_name' => 'Cliente de prueba',
            'status' => 'active',
        ]);
        $work = ProjectWork::create([
            'project_id' => $project->id,
            'name' => 'Obra de prueba',
            'status' => 'active',
            'contract_end_date' => '2026-12-31',
        ]);
        $materialRequest = MaterialRequest::create([
            'folio' => 16500,
            'project_id' => $project->id,
            'zone' => 'Zona de prueba',
            'delivery_address' => 'Dirección de prueba',
            'location_type' => 'sitio',
            'request_date' => '2026-08-27',
            'need_date' => '2026-09-01',
            'supply_category' => 'Materiales',
            'status' => $status,
            'requested_by' => $this->userWithRole('Solmat')->id,
        ]);
        $materialRequest->projectWorks()->sync([$work->id]);
        $item = MaterialRequestItem::create([
            'material_request_id' => $materialRequest->id,
            'code' => 'MAT-001',
            'description' => 'Material de prueba',
            'unit' => 'PZA',
            'quantity' => 4,
        ]);
        MaterialRequestItemProjectWork::create([
            'material_request_item_id' => $item->id,
            'project_work_id' => $work->id,
            'quantity' => 4,
            'is_committed' => false,
        ]);

        return [$materialRequest, $item, $work];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
