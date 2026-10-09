<?php

namespace Tests\Feature;

use App\Http\Controllers\StockController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockLowStockReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('concepts', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description');
            $table->string('warehouse_location');
            $table->decimal('minimum_stock', 14, 3)->nullable();
        });
        $migration = require database_path('migrations/2026_09_29_180000_add_priority_to_concepts_table.php');
        $migration->up();
        foreach (['stock_entry_items', 'stock_exit_items'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('concept_id');
                $table->decimal('quantity', 14, 3);
            });
        }
    }

    public function test_report_calculates_balance_and_excludes_equal_above_or_undefined_minimums(): void
    {
        $below = $this->concept('BAJO', 5);
        $equal = $this->concept('IGUAL', 5);
        $above = $this->concept('SOBRE', 5);
        $this->concept('SIN-MINIMO', null);
        $this->concept('SIN-MOVIMIENTOS', 1);
        $this->concept('MINIMO-CERO', 0);
        DB::table('stock_entry_items')->insert([
            ['concept_id' => $below, 'quantity' => 4],
            ['concept_id' => $below, 'quantity' => 3.5],
            ['concept_id' => $equal, 'quantity' => 5],
            ['concept_id' => $above, 'quantity' => 6],
        ]);
        DB::table('stock_exit_items')->insert([
            ['concept_id' => $below, 'quantity' => 2],
            ['concept_id' => $below, 'quantity' => 1],
        ]);

        $view = app(StockController::class)->lowStock(Request::create('/inventario/reportes/bajo-minimo'));
        $concepts = $view->getData()['concepts'];

        $this->assertSame('stocks.low_stock', $view->name());
        $this->assertSame(2, $concepts->total());
        $this->assertSame(['BAJO', 'SIN-MOVIMIENTOS'], $concepts->pluck('code')->all());
        $this->assertEquals(4.5, $concepts->first()->current_stock);
    }

    public function test_filter_applies_before_pagination_and_search_keeps_the_report_filtered(): void
    {
        $this->concept('AAA-NO-BAJO', 0);
        foreach (range(1, 26) as $number) {
            $this->concept(sprintf('BAJO-%02d', $number), 2);
        }

        $concepts = app(StockController::class)->lowStock(Request::create('/inventario/reportes/bajo-minimo'))->getData()['concepts'];
        $this->assertSame(26, $concepts->total());
        $this->assertCount(25, $concepts->items());
        $this->assertSame('BAJO-01', $concepts->first()->code);

        $filtered = app(StockController::class)->lowStock(Request::create('/inventario/reportes/bajo-minimo', 'GET', ['search' => 'BAJO-26']))->getData()['concepts'];
        $this->assertSame(1, $filtered->total());
        $this->assertSame('BAJO-26', $filtered->first()->code);
    }

    public function test_report_route_retains_inventory_permission_protection(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/inventario/reportes/bajo-minimo'));
        $this->assertSame('stocks.low_stock', $route->getName());
        $this->assertContains('module:stocks', $route->gatherMiddleware());
    }

    public function test_report_orders_high_before_medium_and_low_before_paginating(): void
    {
        $low = $this->concept('AAA-BAJA', 2);
        DB::table('concepts')->where('id', $low)->update(['priority' => 'low']);
        foreach (range(1, 25) as $number) {
            $this->concept(sprintf('MEDIA-%02d', $number), 2);
        }
        $high = $this->concept('ZZZ-ALTA', 2);
        DB::table('concepts')->where('id', $high)->update(['priority' => 'high']);

        $concepts = app(StockController::class)->lowStock(Request::create('/inventario/reportes/bajo-minimo'))->getData()['concepts'];
        $this->assertSame(27, $concepts->total());
        $this->assertSame('ZZZ-ALTA', $concepts->first()->code);
        $this->assertSame('MEDIA-01', $concepts->getCollection()->get(1)->code);
        $this->assertNotContains('AAA-BAJA', $concepts->pluck('code')->all());

        $all = app(StockController::class)->lowStock(Request::create('/inventario/reportes/bajo-minimo', 'GET', ['search' => 'A']))->getData()['concepts'];
        $this->assertSame('high', $all->first()->priority);

        foreach (['low' => 'ORDER-A', 'medium' => 'ORDER-B', 'high' => 'ORDER-C'] as $priority => $code) {
            $id = $this->concept($code, 1);
            DB::table('concepts')->where('id', $id)->update(['priority' => $priority]);
        }
        $ordered = app(StockController::class)->lowStock(Request::create('/inventario/reportes/bajo-minimo', 'GET', ['search' => 'ORDER-']))->getData()['concepts'];
        $this->assertSame(['high', 'medium', 'low'], $ordered->pluck('priority')->all());
    }

    public function test_priority_defaults_to_medium_for_new_and_existing_concepts(): void
    {
        $id = $this->concept('DEFAULT', 1);
        $this->assertSame('medium', DB::table('concepts')->where('id', $id)->value('priority'));
        $this->assertSame('medium', (new \App\Models\Concept())->priority);

        $migration = require database_path('migrations/2026_09_29_180000_add_priority_to_concepts_table.php');
        $migration->down();
        $migration->up();
        $this->assertSame('medium', DB::table('concepts')->where('id', $id)->value('priority'));
    }

    public function test_concept_edit_saves_priority_and_preserves_it_when_omitted(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->string('unit')->default('pza');
            $table->string('status')->default('active');
            $table->string('type')->default('materiales');
            $table->boolean('requires_origin_certificate')->default(false);
            $table->boolean('requires_safety_certificate')->default(false);
            $table->timestamps();
        });
        $id = $this->concept('EDIT', 1);
        $concept = \App\Models\Concept::findOrFail($id);
        $data = [
            'code' => 'EDIT', 'description' => 'Suministro editable', 'unit' => 'pza',
            'warehouse_location' => 'Almacen principal', 'status' => 'active', 'type' => 'materiales',
        ];
        foreach (['high', 'low', 'medium'] as $priority) {
            app(\App\Http\Controllers\ConceptController::class)->update(
                Request::create('/conceptos/' . $id, 'PUT', $data + ['priority' => $priority]), $concept
            );
            $this->assertSame($priority, $concept->fresh()->priority);
        }
        $concept->update(['priority' => 'high']);
        app(\App\Http\Controllers\ConceptController::class)->update(Request::create('/conceptos/' . $id, 'PUT', $data), $concept);
        $this->assertSame('high', $concept->fresh()->priority);
    }

    public function test_invalid_priorities_are_rejected_in_creation_and_editing(): void
    {
        $concept = new \App\Models\Concept();
        foreach (['store', 'update'] as $method) {
            try {
                $request = Request::create('/conceptos', 'POST', ['priority' => 'urgent']);
                $controller = app(\App\Http\Controllers\ConceptController::class);
                if ($method === 'store') {
                    $controller->store($request);
                } else {
                    $controller->update($request, $concept);
                }
                $this->fail('Invalid priority was accepted.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('priority', $exception->errors());
            }
        }
    }

    private function concept(string $code, ?float $minimum): int
    {
        return DB::table('concepts')->insertGetId([
            'code' => $code, 'description' => 'Suministro ' . $code,
            'warehouse_location' => 'Almacen principal', 'minimum_stock' => $minimum,
        ]);
    }
}