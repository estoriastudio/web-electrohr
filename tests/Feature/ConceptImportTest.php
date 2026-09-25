<?php

namespace Tests\Feature;

use App\Imports\ConceptImport;
use App\Models\Concept;
use App\Models\StockEntryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ConceptImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_imported_quantity_as_initial_stock(): void
    {
        $this->actingAs(User::factory()->create());

        (new ConceptImport)->collection(new Collection([
            new Collection([
                'codigo' => 'MAT-001',
                'descripcion' => 'Material de prueba',
                'unidad' => 'pza',
                'ubicacion' => 'Almacén principal',
                'cantidad' => '25.500',
            ]),
        ]));

        $concept = Concept::where('code', 'MAT-001')->firstOrFail();
        $item = StockEntryItem::where('concept_id', $concept->id)->firstOrFail();

        $this->assertSame(25.5, (float) $item->quantity);
        $this->assertSame(25.5, (float) $concept->stockEntryItems()->sum('quantity'));
        $this->assertSame(0.0, (float) $concept->stockExitItems()->sum('quantity'));
    }
}