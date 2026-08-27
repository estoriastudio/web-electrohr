<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\ProjectWork;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_it_creates_an_estimate_for_multiple_project_works(): void
    {
        $project = $this->project();
        $firstWork = $this->work($project, 'Obra norte');
        $secondWork = $this->work($project, 'Obra sur');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('projects.estimates.store', $project), $this->estimateData([
                'estimate_amount' => '1,234.56',
                'allocations' => [
                    ['project_work_id' => $firstWork->id, 'estimate_amount' => '700.00'],
                    ['project_work_id' => $secondWork->id, 'estimate_amount' => '534.56'],
                ],
            ]))
            ->assertRedirect(route('projects.show', $project));

        $estimate = ProjectEstimate::firstOrFail();

        $this->assertSame($project->id, $estimate->project_id);
        $this->assertSame(1234.56, (float) $estimate->estimate_amount);
        $this->assertEqualsCanonicalizing([$firstWork->id, $secondWork->id], $estimate->allocations()->pluck('project_work_id')->all());
        $this->assertDatabaseHas('project_estimate_allocations', [
            'project_estimate_id' => $estimate->id,
            'project_work_id' => $firstWork->id,
            'estimate_amount' => 700,
        ]);
    }

    public function test_it_rejects_allocations_that_do_not_match_the_estimate_amount(): void
    {
        $project = $this->project();
        $work = $this->work($project, 'Obra norte');

        $this->actingAs($this->admin())
            ->from(route('projects.show', $project))
            ->post(route('projects.estimates.store', $project), $this->estimateData([
                'estimate_amount' => '100.00',
                'allocations' => [
                    ['project_work_id' => $work->id, 'estimate_amount' => '99.99'],
                ],
            ]))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('allocations');

        $this->assertDatabaseCount('project_estimates', 0);
    }

    public function test_it_rejects_an_allocation_for_a_work_from_another_project(): void
    {
        $project = $this->project();
        $foreignWork = $this->work($this->project('Otro proyecto'), 'Obra externa');

        $this->actingAs($this->admin())
            ->from(route('projects.show', $project))
            ->post(route('projects.estimates.store', $project), $this->estimateData([
                'allocations' => [
                    ['project_work_id' => $foreignWork->id, 'estimate_amount' => '100.00'],
                ],
            ]))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('allocations');
    }

    public function test_estimate_number_is_unique_per_project_but_can_be_reused_in_another_project(): void
    {
        $project = $this->project();
        $work = $this->work($project, 'Obra norte');
        $admin = $this->admin();
        $project->estimates()->create(array_merge($this->estimateData(), [
            'created_by' => $admin->id,
        ]));

        $this->actingAs($admin)
            ->from(route('projects.show', $project))
            ->post(route('projects.estimates.store', $project), $this->estimateData([
                'allocations' => [['project_work_id' => $work->id, 'estimate_amount' => '100.00']],
            ]))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('estimate_number');

        $otherProject = $this->project('Proyecto dos');
        $otherWork = $this->work($otherProject, 'Obra sur');

        $this->actingAs($admin)
            ->post(route('projects.estimates.store', $otherProject), $this->estimateData([
                'allocations' => [['project_work_id' => $otherWork->id, 'estimate_amount' => '100.00']],
            ]))
            ->assertRedirect(route('projects.show', $otherProject));
    }

    public function test_it_replaces_allocations_when_an_estimate_is_updated(): void
    {
        $project = $this->project();
        $firstWork = $this->work($project, 'Obra norte');
        $secondWork = $this->work($project, 'Obra sur');
        $creator = $this->admin();
        $estimate = $project->estimates()->create(array_merge($this->estimateData(), [
            'created_by' => $creator->id,
        ]));
        $estimate->allocations()->create([
            'project_work_id' => $firstWork->id,
            'estimate_amount' => 100,
        ]);

        $this->actingAs($creator)
            ->put(route('projects.estimates.update', $estimate), $this->estimateData([
                'allocations' => [['project_work_id' => $secondWork->id, 'estimate_amount' => '100.00']],
            ]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('project_estimate_allocations', [
            'project_estimate_id' => $estimate->id,
            'project_work_id' => $firstWork->id,
        ]);
        $this->assertDatabaseHas('project_estimate_allocations', [
            'project_estimate_id' => $estimate->id,
            'project_work_id' => $secondWork->id,
            'estimate_amount' => 100,
        ]);
    }

    public function test_only_the_creator_can_update_an_estimate(): void
    {
        $project = $this->project();
        $work = $this->work($project, 'Obra norte');
        $creator = $this->admin();
        $estimate = $project->estimates()->create(array_merge($this->estimateData(), [
            'created_by' => $creator->id,
        ]));
        $estimate->allocations()->create([
            'project_work_id' => $work->id,
            'estimate_amount' => 100,
        ]);

        $this->actingAs($this->admin())
            ->put(route('projects.estimates.update', $estimate), $this->estimateData([
                'allocations' => [['project_work_id' => $work->id, 'estimate_amount' => '100.00']],
            ]))
            ->assertForbidden();
    }

    public function test_it_stores_documents_in_s3_and_removes_a_replaced_document(): void
    {
        Storage::fake('s3');

        $project = $this->project();
        $work = $this->work($project, 'Obra norte');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('projects.estimates.store', $project), $this->estimateData([
                'allocations' => [['project_work_id' => $work->id, 'estimate_amount' => '100.00']],
            ]))
            ->assertRedirect(route('projects.show', $project));

        $estimate = ProjectEstimate::firstOrFail();

        $this->actingAs($admin)
            ->put(route('projects.estimates.documents.update', $estimate), [
                'invoice_pdf' => UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
                'invoice_xml' => UploadedFile::fake()->create('factura.xml', 10, 'text/xml'),
                'credit_note_pdf' => UploadedFile::fake()->create('nota-credito.pdf', 10, 'application/pdf'),
                'credit_note_xml' => UploadedFile::fake()->create('nota-credito.xml', 10, 'text/xml'),
                'spei_receipt' => UploadedFile::fake()->create('spei.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('projects.show', $project));

        $estimate->refresh();
        $originalInvoicePath = $estimate->invoice_pdf_path;

        foreach ([
            $estimate->invoice_pdf_path,
            $estimate->invoice_xml_path,
            $estimate->credit_note_pdf_path,
            $estimate->credit_note_xml_path,
            $estimate->spei_receipt_path,
        ] as $path) {
            Storage::disk('s3')->assertExists($path);
        }

        $this->actingAs($admin)
            ->put(route('projects.estimates.documents.update', $estimate), [
                'invoice_pdf' => UploadedFile::fake()->create('factura-actualizada.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('projects.show', $project));

        $estimate->refresh();

        Storage::disk('s3')->assertMissing($originalInvoicePath);
        Storage::disk('s3')->assertExists($estimate->invoice_pdf_path);
    }

    private function project(string $name = 'Proyecto de prueba'): Project
    {
        return Project::create([
            'name' => $name,
            'client_name' => 'Cliente de prueba',
            'currency' => 'MXN',
            'status' => 'active',
        ]);
    }

    private function work(Project $project, string $name): ProjectWork
    {
        return ProjectWork::create([
            'project_id' => $project->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function estimateData(array $overrides = []): array
    {
        return array_merge([
            'estimate_number' => 'EST-001',
            'estimate_date' => '2026-08-26',
            'type' => 'estimacion',
            'estimate_amount' => '100.00',
            'status' => 'pendiente',
            'allocations' => [],
        ], $overrides);
    }
}