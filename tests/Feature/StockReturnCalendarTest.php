<?php

namespace Tests\Feature;

use App\Services\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockReturnCalendarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->travelTo(now()->setDate(2026, 9, 29)->startOfDay());

        Schema::create('stock_exits', function (Blueprint $table) {
            $table->id();
            foreach (['concept_id', 'tool_id', 'recipient_worker_id', 'project_id', 'project_work_id', 'return_stock_entry_id', 'created_by'] as $column) {
                $table->unsignedBigInteger($column)->nullable();
            }
            $table->string('exit_type');
            $table->string('voucher_number')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('status');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->date('exited_at');
            $table->date('expected_return_at')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
        Schema::create('concepts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->string('status')->default('active');
            $table->timestamp('archived_at')->nullable();
        });
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->softDeletes();
        });
        foreach (['projects', 'project_works'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            });
        }
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->unsignedBigInteger('tool_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('entry_type');
            $table->decimal('quantity', 14, 3);
            $table->date('received_at');
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function test_supply_loan_stores_the_concept_and_preserves_it_in_calendar_and_return(): void
    {
        DB::table('concepts')->insert(['id' => 1, 'code' => 'SUM-001', 'description' => 'Taladro']);
        DB::table('workers')->insert(['id' => 1, 'first_name' => 'Juan', 'last_name' => 'Perez']);
        DB::table('projects')->insert(['id' => 1, 'name' => 'Proyecto']);
        DB::table('project_works')->insert(['id' => 1, 'name' => 'Obra']);
        $this->mock(NotificationService::class)->shouldReceive('send')->twice();

        $this->post(route('stocks.exits.store'), [
            'exit_type' => 'tool_loan', 'concept_code' => 'SUM-001',
            'voucher_number' => 'LOAN-SUPPLY', 'recipient_name' => 'Juan Pérez',
            'project_id' => 1, 'project_work_id' => 1,
            'exited_at' => '2026-09-29', 'expected_return_at' => '2026-09-30',
        ])->assertRedirect(route('stocks.exits.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_exits', [
            'id' => 1, 'concept_id' => 1, 'tool_id' => null,
            'voucher_number' => 'LOAN-SUPPLY', 'recipient_name' => 'Juan Pérez', 'recipient_worker_id' => null, 'status' => 'open', 'quantity' => 1,
        ]);
        $this->getJson(route('stocks.exits.calendar.events', [
            'start' => '2026-09-01', 'end' => '2026-10-01',
        ]))->assertOk()->assertJsonPath('0.title', 'LOAN-SUPPLY - SUM-001')
            ->assertJsonPath('0.extendedProps.tool', 'SUM-001 - Taladro');

        $response = app(\App\Http\Controllers\StockExitController::class)->returnTool(
            Request::create(route('stocks.exits.return', ['stockExit' => 1]), 'POST', ['received_at' => '2026-09-30']),
            \App\Models\StockExit::findOrFail(1)
        );
        $this->assertSame(route('stocks.exits.index'), $response->getTargetUrl());
        $this->assertDatabaseHas('stock_entries', [
            'concept_id' => 1, 'tool_id' => null, 'entry_type' => 'tool_return', 'quantity' => 1,
        ]);
        $this->assertDatabaseHas('stock_exits', ['id' => 1, 'status' => 'returned', 'return_stock_entry_id' => 1]);
    }

    public function test_supply_loan_can_be_registered_without_voucher_number(): void
    {
        DB::table('concepts')->insert(['id' => 1, 'code' => 'SUM-001', 'description' => 'Taladro']);
        DB::table('projects')->insert(['id' => 1, 'name' => 'Proyecto']);
        DB::table('project_works')->insert(['id' => 1, 'name' => 'Obra']);
        $this->mock(NotificationService::class)->shouldReceive('send')->once();

        $this->post(route('stocks.exits.store'), [
            'exit_type' => 'tool_loan', 'concept_code' => 'SUM-001', 'recipient_name' => 'Juan Pérez',
            'project_id' => 1, 'project_work_id' => 1,
            'exited_at' => '2026-09-29', 'expected_return_at' => '2026-09-30',
        ])->assertRedirect(route('stocks.exits.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_exits', ['id' => 1, 'voucher_number' => null]);
        $this->getJson(route('stocks.exits.calendar.events', [
            'start' => '2026-09-01', 'end' => '2026-10-01',
        ]))->assertOk()->assertJsonPath('0.title', 'SUM-001')
            ->assertJsonPath('0.extendedProps.voucher', '—');
    }

    public function test_supply_loan_requires_an_existing_supply_code_instead_of_a_tool_id(): void
    {
        foreach ([null, 'UNKNOWN'] as $code) {
            $this->postJson(route('stocks.exits.store'), [
                'exit_type' => 'tool_loan', 'concept_code' => $code, 'tool_id' => 1,
                'voucher_number' => 'INVALID', 'exited_at' => '2026-09-29',
            ])->assertUnprocessable()->assertJsonValidationErrors('concept_code');
        }
        $this->assertDatabaseCount('stock_exits', 0);
    }

    public function test_feed_only_returns_tool_loans_in_the_visible_date_range(): void
    {
        foreach ([
            ['LOAN-OVERDUE', 'tool_loan', 'open', '2026-09-01'],
            ['LOAN-PENDING', 'tool_loan', 'open', '2026-09-30'],
            ['LOAN-RETURNED', 'tool_loan', 'returned', '2026-09-10'],
            ['OUTSIDE', 'tool_loan', 'open', '2026-10-01'],
            ['DEFINITIVE', 'definitive', 'completed', '2026-09-15'],
            ['NO-DATE', 'tool_loan', 'open', null],
        ] as [$voucher, $type, $status, $date]) {
            DB::table('stock_exits')->insert([
                'voucher_number' => $voucher, 'exit_type' => $type,
                'status' => $status, 'exited_at' => '2026-08-01', 'expected_return_at' => $date,
            ]);
        }

        $response = $this->getJson(route('stocks.exits.calendar.events', [
            'start' => '2026-09-01T00:00:00-06:00', 'end' => '2026-10-01T00:00:00-06:00',
        ]));

        $response->assertOk()->assertJsonCount(3)
            ->assertJsonPath('0.start', '2026-09-01')
            ->assertJsonPath('0.extendedProps.status', 'Retorno vencido')
            ->assertJsonPath('1.extendedProps.status', 'Devuelta')
            ->assertJsonPath('2.extendedProps.status', 'Pendiente de retorno')
            ->assertJsonPath('2.allDay', true);
    }

    public function test_feed_rejects_missing_or_reversed_dates(): void
    {
        $this->getJson(route('stocks.exits.calendar.events'))
            ->assertUnprocessable()->assertJsonValidationErrors(['start', 'end']);
        $this->getJson(route('stocks.exits.calendar.events', ['start' => '2026-10-01', 'end' => '2026-09-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('end');
    }

    public function test_calendar_routes_are_not_captured_by_inventory_detail(): void
    {
        foreach (['stocks.exits.calendar', 'stocks.exits.calendar.events'] as $name) {
            $route = app('router')->getRoutes()->match(Request::create(route($name), 'GET'));
            $this->assertSame($name, $route->getName());
            $this->assertContains('module:stocks', $route->gatherMiddleware());
        }
    }
}