<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\ProjectWorkEstimate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_it_accepts_an_estimate_amount_with_thousands_separator_and_decimals(): void
    {
        $project = Project::create([
            'name' => 'Proyecto de prueba',
            'client_name' => 'Cliente de prueba',
            'status' => 'active',
        ]);
        $projectWork = ProjectWork::create([
            'project_id' => $project->id,
            'name' => 'Obra de prueba',
            'status' => 'active',
            'contract_end_date' => '2026-09-01',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('estimates.store', $projectWork), [
                'estimate_number' => 'EST-001',
                'estimate_date' => '2026-08-21',
                'type' => 'estimacion',
                'estimate_amount' => '1,234.56',
                'status' => 'pendiente',
            ])
            ->assertRedirect(route('project_works.show', $projectWork));

        $estimate = ProjectWorkEstimate::firstOrFail();

        $this->assertSame(1234.56, (float) $estimate->estimate_amount);
    }
}