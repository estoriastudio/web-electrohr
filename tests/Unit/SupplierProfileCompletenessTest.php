<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierLocation;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SupplierProfileCompletenessTest extends TestCase
{
    public function test_profile_completeness_uses_the_supplier_address(): void
    {
        $supplier = new Supplier([
            'rfc_name' => 'Proveedor de Prueba S.A. de C.V.',
            'commercial_name' => 'Proveedor de Prueba',
            'rfc_num' => 'PPR010101AAA',
            'attended_by' => 'Compras',
            'status' => 'active',
            'street' => 'Calle Principal 123',
        ]);

        $supplier->setRelation('contacts', new Collection([
            new SupplierContact([
                'name' => 'Contacto principal',
                'email' => 'contacto@example.test',
                'phone' => '5555555555',
                'is_primary' => true,
            ]),
        ]));
        $supplier->setRelation('locations', new Collection([
            new SupplierLocation([
                'name' => 'Cuenta principal',
                'bank_name' => 'Banco de Prueba',
                'bank_account' => '1234567890',
                'bank_clabe' => '123456789012345678',
                'currency' => 'MXN',
            ]),
        ]));

        $this->assertSame(100, $supplier->profile_completeness);
        $this->assertNotContains('Domicilio', $supplier->missing_fields);
    }
}