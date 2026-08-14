<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectAgreementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_an_agreement_for_selected_project_works(): void
    {
        Storage::fake('s3');
        $project = Project::create([
            'name' => 'Proyecto de prueba',
            'client_name' => 'Cliente de prueba',
            'status' => 'active',
        ]);
        $firstWork = $this->work($project, 'Obra norte', '2026-09-01');
        $secondWork = $this->work($project, 'Obra sur', '2026-10-01');
        $unselectedWork = $this->work($project, 'Obra sin convenio', '2026-11-01');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('projects.agreements.store', $project), [
                'work_ids' => [$firstWork->id, $secondWork->id],
                'contracted_amount' => 1000,
                'current_amount' => 1200,
                'contracted_end_date' => '2026-09-01',
                'contracted_term_days' => 180,
                'current_end_date' => '2026-10-01',
                'agreement_number' => 'CONV-2026-001',
                'new_amount' => 1500,
                'new_end_date' => '2026-12-01',
                'appointments_file' => UploadedFile::fake()->create('nombramientos.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_agreements', [
            'project_id' => $project->id,
            'agreement_number' => 'CONV-2026-001',
            'new_amount' => 1500,
        ]);

        $agreement = $project->agreements()->firstOrFail();
        $this->assertEqualsCanonicalizing([$firstWork->id, $secondWork->id], $agreement->works()->pluck('id')->all());
        $this->assertSame(1500.0, (float) $project->fresh()->current_agreement_value);
        $this->assertSame('2026-12-01', $firstWork->fresh()->contract_end_date);
        $this->assertSame('2026-12-01', $secondWork->fresh()->contract_end_date);
        $this->assertSame('2026-11-01', $unselectedWork->fresh()->contract_end_date);
        Storage::disk('s3')->assertExists($agreement->appointments_file_path);
    }

    public function test_agreement_cannot_include_a_work_from_another_project(): void
    {
        Storage::fake('s3');
        $project = Project::create([
            'name' => 'Proyecto de prueba',
            'client_name' => 'Cliente de prueba',
            'status' => 'active',
        ]);
        $otherProject = Project::create([
            'name' => 'Otro proyecto',
            'client_name' => 'Otro cliente',
            'status' => 'active',
        ]);
        $foreignWork = $this->work($otherProject, 'Obra externa', '2026-10-01');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->from(route('projects.show', $project))
            ->post(route('projects.agreements.store', $project), [
                'work_ids' => [$foreignWork->id],
                'contracted_amount' => 1000,
                'current_amount' => 1000,
                'contracted_end_date' => '2026-09-01',
                'contracted_term_days' => 180,
                'current_end_date' => '2026-09-01',
                'agreement_number' => 'CONV-INVALIDO',
                'new_amount' => 1500,
                'new_end_date' => '2026-12-01',
                'appointments_file' => UploadedFile::fake()->create('nombramientos.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('work_ids');

        $this->assertDatabaseCount('project_agreements', 0);
    }

    private function work(Project $project, string $name, string $contractEndDate): ProjectWork
    {
        return ProjectWork::create([
            'project_id' => $project->id,
            'name' => $name,
            'status' => 'active',
            'contract_end_date' => $contractEndDate,
        ]);
    }
}