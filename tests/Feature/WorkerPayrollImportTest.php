<?php

namespace Tests\Feature;

use App\Imports\WorkerPayrollImport;
use App\Models\PositionCategory;
use App\Models\Project;
use App\Models\ProjectWork;
use App\Models\Worker;
use App\Models\WorkerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WorkerPayrollImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_worker_data_and_separates_same_named_groups_by_project_work(): void
    {
        $project = Project::create([
            'name' => 'Proyecto de prueba',
            'client_name' => 'Cliente de prueba',
            'status' => 'active',
        ]);
        $firstWork = ProjectWork::create(['project_id' => $project->id, 'name' => 'LUGAR NORTE', 'status' => 'active']);
        $secondWork = ProjectWork::create(['project_id' => $project->id, 'name' => 'LUGAR SUR', 'status' => 'active']);
        $rows = new Collection([
            ['', '', '', '', '', '', '', '', '', '', ''],
            ['LUGAR', 'No. CUENTA', 'APELLDO PATERNO', 'APELLINO MATERNO', 'NOMBRE', 'CATEGORIA', 'SUELDO', 'No. DE SEGURO SOCIAL', 'CURP', 'CUADRILLA', 'FECHA DE INGRESO'],
            ['LUGAR NORTE', '1444396774', 'LOZA', 'OROZCO', 'FERMIN', 'GERENTE DE OBRA', '$ 10,000.00', '41-91-76-0197-7', 'LOOF760210HSPZRR03', 'ADMINISTRATIVO', '2/1/2014'],
            ['LUGAR SUR', '1444396537', 'CASTILLO', 'PEREZ', 'GUSTAVO ADOLFO', 'GERENTE DE OBRA', '$ 7,000.00', '12-09-84-0915-9', 'CAPG840718HGTSRS03', 'ADMINISTRATIVO', '4/13/2016'],
        ]);

        $import = app(WorkerPayrollImport::class);
        $import->collection($rows);
        $import->collection($rows);
        $import->collection(new Collection([
            ['LUGAR', 'No. CUENTA', 'APELLDO PATERNO', 'APELLINO MATERNO', 'NOMBRE', 'CATEGORIA', 'SUELDO', 'No. DE SEGURO SOCIAL', 'CURP', 'CUADRILLA', 'FECHA DE INGRESO'],
            ['LUGAR NORTE', '1444396999', 'LOZA', 'OROZCO', 'FERMIN ACTUALIZADO', 'GERENTE DE OBRA', '$ 11,000.00', '41-91-76-0197-7', 'LOOF760210HSPZRR03', 'ADMINISTRATIVO', '2/1/2014'],
        ]));

        $category = PositionCategory::where('name', 'GERENTE DE OBRA')->firstOrFail();
        $worker = Worker::where('nss', '41-91-76-0197-7')->firstOrFail();

        $this->assertSame('1444396999', $worker->employee_code);
        $this->assertSame('FERMIN ACTUALIZADO', $worker->first_name);
        $this->assertSame('LOZA OROZCO', $worker->last_name);
        $this->assertSame('41-91-76-0197-7', $worker->nss);
        $this->assertSame('LOOF760210HSPZRR03', $worker->curp);
        $this->assertSame('2014-02-01', $worker->hire_date->toDateString());
        $this->assertSame('active', $worker->status);
        $this->assertSame($category->id, $worker->position_category_id);
        $this->assertSame(11000.0, (float) $worker->weekly_salary);
        $this->assertSame(1, Worker::where('nss', '41-91-76-0197-7')->count());
        $this->assertSame(2, WorkerGroup::where('name', 'ADMINISTRATIVO')->count());
        $this->assertDatabaseHas('worker_groups', ['name' => 'ADMINISTRATIVO', 'project_work_id' => $firstWork->id]);
        $this->assertDatabaseHas('worker_groups', ['name' => 'ADMINISTRATIVO', 'project_work_id' => $secondWork->id]);
        $this->assertSame(2, $worker->groups()->count());
        $this->assertSame(1, $worker->groups()->wherePivotNull('left_at')->count());
    }

    public function test_it_skips_rows_without_an_nss(): void
    {
        $rows = new Collection([
            ['LUGAR', 'No. CUENTA', 'APELLDO PATERNO', 'APELLINO MATERNO', 'NOMBRE', 'CATEGORIA', 'SUELDO', 'No. DE SEGURO SOCIAL', 'CURP', 'CUADRILLA', 'FECHA DE INGRESO'],
            ['LUGAR NORTE', '1444396774', 'LOZA', 'OROZCO', 'FERMIN', 'GERENTE DE OBRA', '$ 10,000.00', '', 'LOOF760210HSPZRR03', 'ADMINISTRATIVO', '2/1/2014'],
        ]);

        $import = app(WorkerPayrollImport::class);
        $import->collection($rows);

        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1, 'warnings' => 1], $import->summary());
        $this->assertDatabaseCount('workers', 0);
    }
}
