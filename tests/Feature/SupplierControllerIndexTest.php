<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_index_reports_coverage_and_filters_suppliers_missing_a_profile_field(): void
    {
        $user = $this->authorizedUser();
        $completeSupplier = $this->createCompleteSupplier('Proveedor completo');
        $incompleteSupplier = Supplier::create([
            'rfc_name' => 'Proveedor sin RFC',
            'commercial_name' => 'Sin RFC',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('suppliers.index', ['document_missing' => 'rfc_num']))
            ->assertOk()
            ->assertViewHas('documentCoverage', function (array $coverage) {
                return $coverage['rfc_num'] === ['completed' => 1, 'missing' => 1];
            })
            ->assertViewHas('suppliers', function ($suppliers) use ($completeSupplier, $incompleteSupplier) {
                return $suppliers->total() === 1
                    && $suppliers->first()->is($incompleteSupplier)
                    && !$suppliers->first()->is($completeSupplier);
            });
    }

    public function test_index_filters_suppliers_missing_contact_and_account_statement_requirements(): void
    {
        $user = $this->authorizedUser();
        $completeSupplier = $this->createCompleteSupplier('Proveedor completo');
        $missingContactSupplier = Supplier::create([
            'rfc_name' => 'Proveedor sin contacto',
            'commercial_name' => 'Sin contacto',
            'rfc_num' => 'XAXX010101000',
            'status' => 'active',
        ]);
        $missingStatementSupplier = Supplier::create([
            'rfc_name' => 'Proveedor sin carátula',
            'commercial_name' => 'Sin carátula',
            'rfc_num' => 'XEXX010101000',
            'status' => 'active',
        ]);
        $missingStatementSupplier->contacts()->create([
            'name' => 'Contacto sin carátula',
            'phone' => '5555555555',
            'email' => 'contacto@example.com',
            'is_primary' => true,
        ]);
        $missingStatementSupplier->locations()->create([
            'name' => 'Cuenta principal',
            'currency' => 'MXN',
        ]);

        $this->actingAs($user)
            ->get(route('suppliers.index', ['document_missing' => 'contact_phone']))
            ->assertOk()
            ->assertViewHas('suppliers', function ($suppliers) use ($missingContactSupplier) {
                return $suppliers->total() === 1 && $suppliers->first()->is($missingContactSupplier);
            });

        $this->actingAs($user)
            ->get(route('suppliers.index', ['document_missing' => 'account_statement']))
            ->assertOk()
            ->assertViewHas('suppliers', function ($suppliers) use ($completeSupplier, $missingStatementSupplier) {
                return $suppliers->total() === 2
                    && $suppliers->contains($missingStatementSupplier)
                    && !$suppliers->contains($completeSupplier);
            });
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function createCompleteSupplier(string $name): Supplier
    {
        $supplier = Supplier::create([
            'rfc_name' => $name,
            'commercial_name' => $name . ' Comercial',
            'rfc_num' => 'AAA010101AAA',
            'street' => 'Calle Uno',
            'postal_code' => '01000',
            'colony' => 'Centro',
            'city' => 'Ciudad de Mexico',
            'state' => 'Ciudad de Mexico',
            'status' => 'active',
        ]);

        $supplier->contacts()->create([
            'name' => 'Contacto principal',
            'phone' => '5512345678',
            'email' => 'principal@example.com',
            'is_primary' => true,
        ]);
        $supplier->locations()->create([
            'name' => 'Cuenta principal',
            'bank_name' => 'Banco de prueba',
            'bank_account' => '1234567890',
            'bank_clabe' => '123456789012345678',
            'currency' => 'MXN',
            'account_statement_path' => 'supplier_locations/account_statement.pdf',
        ]);

        return $supplier;
    }
}