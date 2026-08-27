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
            'status' => 'active',
            'street' => 'Calle Principal 123',
            'postal_code' => '01000',
            'colony' => 'Centro',
            'city' => 'Ciudad de México',
            'state' => 'Ciudad de México',
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
                'account_statement_path' => 'supplier_locations/1/account_statements/1.pdf',
            ]),
        ]));

        $this->assertSame(100, $supplier->profile_completeness);
        $this->assertSame([], $supplier->missing_fields);
        $this->assertSame([], $supplier->purchaseOrderMissingFields());
    }

    public function test_purchase_order_readiness_uses_the_profile_requirements(): void
    {
        $supplier = new Supplier([
            'rfc_name' => 'Proveedor de Prueba S.A. de C.V.',
            'commercial_name' => 'Proveedor de Prueba',
            'rfc_num' => 'PPR010101AAA',
            'status' => 'active',
            'street' => 'Calle Principal 123',
            'postal_code' => '01000',
            'colony' => 'Centro',
            'state' => 'Ciudad de México',
        ]);

        $supplier->setRelation('contacts', new Collection([
            new SupplierContact([
                'name' => 'Contacto principal',
                'email' => 'contacto@example.test',
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

        $missingFields = [
            'Ciudad',
            'Teléfono del contacto principal',
            'Carátula de estado de cuenta',
        ];

        $this->assertSame($missingFields, $supplier->missing_fields);
        $this->assertSame($missingFields, $supplier->purchaseOrderMissingFields());
        $this->assertSame(82, $supplier->profile_completeness);
    }
}