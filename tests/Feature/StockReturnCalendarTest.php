<?php

namespace Tests\Feature;

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
            foreach (['tool_id', 'recipient_worker_id', 'project_id', 'project_work_id', 'return_stock_entry_id'] as $column) {
                $table->unsignedBigInteger($column)->nullable();
            }
            $table->string('exit_type');
            $table->string('voucher_number');
            $table->string('recipient_name')->nullable();
            $table->string('status');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->date('exited_at');
            $table->date('expected_return_at')->nullable();
            $table->text('observations')->nullable();
        });
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
            $this->assertContains('role:admin|Inventario', $route->gatherMiddleware());
        }
    }
}