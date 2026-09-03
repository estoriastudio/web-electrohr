<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SupplierPortalInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_and_accepted_invoices_reserve_purchase_order_balance(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create([
            'rfc_name' => 'Proveedor de prueba',
            'portal_user_id' => $user->id,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'folio' => 12345,
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 100,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);

        $this->createInvoice($purchaseOrder, PurchaseOrderInvoice::STATUS_ACEPTADA, 20);
        $this->createInvoice($purchaseOrder, PurchaseOrderInvoice::STATUS_EN_PROCESO, 70);

        $uuid = '11111111-1111-4111-8111-111111111111';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Fecha="2026-08-19">
    <cfdi:Complemento>
        <tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" UUID="{$uuid}" />
    </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $this->actingAs($user)
            ->post(route('supplier_portal.invoices.store', $purchaseOrder), [
                'due_date' => '2026-09-01',
                'folio' => $uuid,
                'amount' => 20,
                'pdf_file' => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
                'xml_file' => UploadedFile::fake()->createWithContent('factura.xml', $xml),
                'evidence_file' => UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('purchase_order_invoices', 2);
    }

    public function test_supplier_can_invoice_the_same_rounded_total_shown_in_purchase_order_detail(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create([
            'rfc_name' => 'Proveedor de prueba',
            'portal_user_id' => $user->id,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'folio' => 12346,
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 10.00,
            'tax_rate' => 16,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'description' => 'Concepto de prueba',
            'unit' => 'PZA',
            'quantity' => 1,
            'unit_price' => 10.005,
        ]);

        $this->assertSame(11.61, $purchaseOrder->fresh()->total_with_iva);

        $uuid = '22222222-2222-4222-8222-222222222222';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Fecha="2026-08-19">
    <cfdi:Complemento>
        <tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" UUID="{$uuid}" />
    </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $this->actingAs($user)
            ->post(route('supplier_portal.invoices.store', $purchaseOrder), [
                'due_date' => '2026-09-01',
                'folio' => $uuid,
                'amount' => 11.61,
                'pdf_file' => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
                'xml_file' => UploadedFile::fake()->createWithContent('factura.xml', $xml),
                'evidence_file' => UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('supplier_portal.purchase_orders.index'));

        $this->assertDatabaseHas('purchase_order_invoices', [
            'purchase_order_id' => $purchaseOrder->id,
            'folio' => $uuid,
            'amount' => 11.61,
            'net_scope' => 11.61,
        ]);
    }

    public function test_supplier_invoice_amount_is_normalized_to_the_purchase_order_total(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create([
            'rfc_name' => 'Proveedor de prueba',
            'portal_user_id' => $user->id,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'folio' => 12347,
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => 'MXN',
            'amount' => 8200,
            'tax_rate' => 16,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'description' => 'Concepto de prueba',
            'unit' => 'PZA',
            'quantity' => 1,
            'unit_price' => 7068.972,
        ]);

        $this->assertSame(8200.0, $purchaseOrder->fresh()->total_with_iva);

        $uuid = '33333333-3333-4333-8333-333333333333';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Fecha="2026-08-19">
    <cfdi:Complemento>
        <tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" UUID="{$uuid}" />
    </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $this->actingAs($user)
            ->post(route('supplier_portal.invoices.store', $purchaseOrder), [
                'due_date' => '2026-09-01',
                'folio' => $uuid,
                'amount' => 8200.01,
                'pdf_file' => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
                'xml_file' => UploadedFile::fake()->createWithContent('factura.xml', $xml),
                'evidence_file' => UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('supplier_portal.purchase_orders.index'));

        $this->assertDatabaseHas('purchase_order_invoices', [
            'purchase_order_id' => $purchaseOrder->id,
            'folio' => $uuid,
            'amount' => 8200.00,
            'net_scope' => 8200.00,
        ]);
    }

    private function createInvoice(PurchaseOrder $purchaseOrder, string $status, float $amount): void
    {
        PurchaseOrderInvoice::create([
            'purchase_order_id' => $purchaseOrder->id,
            'folio' => fake()->uuid(),
            'status' => $status,
            'file_name' => 'factura.pdf',
            'file_path' => 'invoices/factura.pdf',
            'amount' => $amount,
            'net_scope' => $amount,
            'currency' => 'MXN',
        ]);
    }
}