<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['material_requests', 'purchase_requests', 'purchase_orders'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('folio');
                $table->softDeletes();
            });
        }
    }

    public function test_search_redirects_to_the_selected_resource_by_exact_folio(): void
    {
        $this->actingAs(User::factory()->make());

        foreach ([
            ['solmat', 'material_requests', 'material_requests.show'],
            ['solcom', 'purchase_requests', 'purchase_requests.show'],
            ['purchase_order', 'purchase_orders', 'purchase_orders.show'],
        ] as [$type, $table, $route]) {
            DB::table($table)->insert(['id' => 1, 'folio' => 'FOLIO-100-extra']);
            DB::table($table)->insert(['id' => 2, 'folio' => 'FOLIO-100']);

            $this->get(route('global_search', [
                'search_resource' => $type, 'search_folio' => ' FOLIO-100 ',
            ]))->assertRedirect(route($route, 2));
        }
    }

    public function test_missing_and_deleted_folios_return_an_error_and_preserve_input(): void
    {
        $this->actingAs(User::factory()->make());
        DB::table('material_requests')->insert([
            'folio' => 'DELETED', 'deleted_at' => now(),
        ]);

        foreach (['MISSING', 'DELETED'] as $folio) {
            $this->from('/configuracion')->get(route('global_search', [
                'search_resource' => 'solmat', 'search_folio' => $folio,
            ]))->assertRedirect('/configuracion')
                ->assertSessionHasErrors(['search_folio'], null, 'generalSearch')
                ->assertSessionHas('_old_input.search_resource', 'solmat')
                ->assertSessionHas('_old_input.search_folio', $folio);
        }
    }

    public function test_invalid_resource_and_empty_folio_are_rejected(): void
    {
        $this->actingAs(User::factory()->make());

        $this->get(route('global_search', [
            'search_resource' => 'users', 'search_folio' => '100',
        ]))->assertSessionHasErrors(['search_resource'], null, 'generalSearch');

        $this->get(route('global_search', [
            'search_resource' => 'solcom', 'search_folio' => '   ',
        ]))->assertSessionHasErrors(['search_folio'], null, 'generalSearch');
    }

    public function test_guests_cannot_search(): void
    {
        $this->get(route('global_search', [
            'search_resource' => 'solmat', 'search_folio' => '100',
        ]))->assertRedirect(route('login'));
    }
}