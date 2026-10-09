<?php

namespace Tests\Feature;

use App\Exports\SupplierAccountStatementExport;
use App\Http\Controllers\SupplierAccountStatementController;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use App\Models\User;
use App\Services\SupplierAccountStatementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierAccountStatementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });
        $migration = require database_path('migrations/2026_04_23_225441_create_permission_tables.php');
        $migration->up();
        $supplierRead = \Spatie\Permission\Models\Permission::create(['name' => 'suppliers.read', 'guard_name' => 'web']);
        foreach (['admin', 'Pagos', 'Orden de compra', 'supplier_portal_access'] as $role) {
            $created = Role::create(['name' => $role, 'guard_name' => 'web']);
            if (in_array($role, ['Pagos', 'Orden de compra'], true)) {
                $created->givePermissionTo($supplierRead);
            }
        }
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('rfc_name');
            $table->string('commercial_name')->nullable();
            $table->unsignedBigInteger('portal_user_id')->nullable();
            $table->softDeletes();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->softDeletes();
        });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->string('project')->nullable();
            $table->string('elaborated_by')->nullable();
            $table->string('currency')->default('MXN');
            $table->string('status')->default('autorizada');
            $table->decimal('amount', 15, 2);
            $table->timestamp('archived_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('purchase_order_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('concept')->nullable();
            $table->string('payment_condition')->default('credito');
            $table->string('value_type')->default('fijo');
            $table->decimal('value', 15, 2)->default(100);
            $table->decimal('covered_amount', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('milestone_id');
            $table->string('folio')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status');
            $table->date('payment_date')->nullable();
            $table->string('spei_receipt_path')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('folio')->nullable();
            $table->string('status')->default('aceptada');
            $table->string('currency')->default('MXN');
            $table->decimal('amount', 15, 2);
            $table->decimal('credit_note_amount', 15, 2)->nullable();
            $table->decimal('net_scope', 15, 2)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
        Schema::create('invoice_milestone', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_invoice_id');
            $table->unsignedBigInteger('purchase_order_milestone_id');
        });
        DB::table('suppliers')->insert([
            ['id' => 1, 'rfc_name' => 'Kobrex'], ['id' => 2, 'rfc_name' => 'Otro proveedor'],
        ]);
        DB::table('projects')->insert([
            ['id' => 1, 'name' => 'Atequiza'], ['id' => 2, 'name' => 'Otro proyecto'],
        ]);
    }

    private function movement(array $attributes = [], string $status = 'pagado'): array
    {
        $order = PurchaseOrder::create(array_merge([
            'folio' => '25001', 'supplier_id' => 1, 'project_id' => 1,
            'currency' => 'MXN', 'amount' => 1000,
        ], $attributes));
        $milestone = PurchaseOrderMilestone::create(['purchase_order_id' => $order->id]);
        $invoice = PurchaseOrderInvoice::create([
            'purchase_order_id' => $order->id, 'amount' => 600, 'status' => 'aceptada',
            'folio' => 'FACT-' . $order->id, 'currency' => $order->currency,
        ]);
        $invoice->milestones()->attach($milestone);
        $payment = Payment::create(['milestone_id' => $milestone->id, 'amount' => 400, 'status' => $status]);
        Payment::create(['milestone_id' => $milestone->id, 'amount' => 200, 'status' => 'por_autorizar']);
        return [$order, $milestone, $invoice, $payment];
    }

    public function test_filters_archived_orders_currency_and_pagination_share_the_same_totals(): void
    {
        [$first] = $this->movement(['archived_at' => now()]);
        $this->movement(['supplier_id' => 2]);
        $this->movement(['project_id' => 2]);
        $this->movement(['currency' => 'USD']);
        [$deleted] = $this->movement();
        $deleted->delete();
        $service = app(SupplierAccountStatementService::class);
        $result = $service->report(['supplier_id' => 1, 'project_id' => 1, 'currency' => 'MXN'], 1, 25);
        $this->assertSame(1, $result['rows']->total());
        $this->assertSame($first->id, $result['rows'][0]['order_id']);
        $this->assertSame(60000, $result['summary']['MXN']['invoiced']);
        $this->assertSame(40000, $result['summary']['MXN']['paid']);
        $this->assertSame(20000, $result['summary']['MXN']['pending']);
        $all = $service->report(['supplier_id' => 1], 1, 1);
        $secondPage = $service->report(['supplier_id' => 1], 2, 1);
        $this->assertSame($all['summary'], $secondPage['summary']);
        $this->assertArrayHasKey('USD', $all['summary']);
        $this->assertSame(3, $all['rows']->total());
    }

    public function test_report_modal_and_excel_do_not_write_financial_records(): void
    {
        [$order] = $this->movement();
        $before = $this->financialState();
        $service = app(SupplierAccountStatementService::class);
        $service->report([], 1, 25);
        $service->purchaseOrderSummary($order);
        $export = new SupplierAccountStatementExport($service, []);
        $data = iterator_to_array($export->generator());
        $this->assertSame(400, $data[0][13]);
        $this->assertSame(200, $data[0][14]);
        $this->assertSame($before, $this->financialState());
    }

    public function test_several_invoices_for_one_milestone_are_consistent_when_their_sum_matches_the_payments(): void
    {
        [$order, $milestone, $first] = $this->movement();
        $first->update(['amount' => 400]);
        $second = PurchaseOrderInvoice::create([
            'purchase_order_id' => $order->id, 'amount' => 200, 'status' => 'aceptada', 'currency' => 'MXN',
        ]);
        $second->milestones()->attach($milestone);
        $result = app(SupplierAccountStatementService::class)->purchaseOrderSummary($order->fresh());
        $this->assertSame(['pagado', 'pendiente'], $result['rows']->pluck('status')->all());
        $this->assertSame(20000, $result['economic']['pending_invoices']);
    }

    public function test_admin_payments_and_purchasing_can_access_the_report_but_other_roles_cannot(): void
    {
        [$order] = $this->movement();
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $user->assignRole('admin');
        $before = $this->financialState();
        $this->actingAs($user)->get(route('suppliers.account_statement.order', $order))
            ->assertOk()->assertSee('Importe facturado sin pagar')->assertSee('200.00');
        $this->assertSame($before, $this->financialState());

        $controller = \Mockery::mock(SupplierAccountStatementController::class, [app(SupplierAccountStatementService::class)])->makePartial();
        $controller->shouldReceive('index')->andReturn(view('suppliers.partials._account_statement_status', ['status' => 'pagado']));
        $controller->shouldReceive('export')->andReturn(response('Exportacion permitida'));
        $this->app->instance(SupplierAccountStatementController::class, $controller);

        foreach (['admin', 'Pagos', 'Orden de compra'] as $role) {
            $user->syncRoles([$role]);
            foreach (['index', 'export', 'order'] as $endpoint) {
                $this->get(route('suppliers.account_statement.' . $endpoint, $endpoint === 'order' ? $order : []))->assertOk();
            }
        }
        $user->syncRoles(['supplier_portal_access']);
        foreach (['index', 'export', 'order'] as $endpoint) {
            $this->get(route('suppliers.account_statement.' . $endpoint, $endpoint === 'order' ? $order : []))->assertForbidden();
        }
        $user->syncRoles([]);
        foreach (['index', 'export', 'order'] as $endpoint) {
            $this->get(route('suppliers.account_statement.' . $endpoint, $endpoint === 'order' ? $order : []))->assertForbidden();
        }
        $this->assertSame($before, $this->financialState());
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'suppliers.account_statement.')) {
                $this->assertSame(['GET', 'HEAD'], $route->methods());
            }
        }
    }

    public function test_controller_filters_and_numeric_excel_output_are_consistent(): void
    {
        $buyer = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $this->movement(['buyer_id' => $buyer->id]);
        $this->movement(['supplier_id' => 2]);
        $request = Request::create('/proveedores/estado-cuenta', 'GET', [
            'supplier_id' => 1, 'buyer_id' => $buyer->id, 'order' => '25001', 'status' => 'pendiente',
        ]);
        $data = app(SupplierAccountStatementController::class)->index($request)->getData();
        $this->assertSame(1, $data['rows']->total());
        $this->assertSame(20000, $data['summary']['MXN']['pending']);
        $bytes = Excel::raw(new SupplierAccountStatementExport(app(SupplierAccountStatementService::class), $data['filters']), \Maatwebsite\Excel\Excel::XLSX);
        $file = tempnam(sys_get_temp_dir(), 'statement-test-');
        try {
            file_put_contents($file, $bytes);
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder());
            $sheet = IOFactory::load($file)->getActiveSheet();
            $this->assertEquals(400, $sheet->getCell('N2')->getValue());
            $this->assertEquals(200, $sheet->getCell('O2')->getValue());
            $this->assertSame('n', $sheet->getCell('N2')->getDataType());
        } finally {
            unlink($file);
        }
    }

    public function test_review_filter_is_read_only_and_combines_with_the_current_order_filters(): void
    {
        $buyer = User::create(['name' => 'Comprador', 'email' => 'review@example.com', 'password' => 'password']);
        [$order, $milestone] = $this->movement(['buyer_id' => $buyer->id]);
        $secondInvoice = PurchaseOrderInvoice::create([
            'purchase_order_id' => $order->id, 'amount' => 300, 'status' => 'aceptada', 'currency' => 'MXN',
        ]);
        $secondInvoice->milestones()->attach($milestone);
        $this->movement(['supplier_id' => 2, 'buyer_id' => $buyer->id]);
        $this->movement(['currency' => 'USD', 'buyer_id' => $buyer->id]);
        $before = $this->financialState();
        $request = Request::create('/proveedores/estado-cuenta', 'GET', [
            'supplier_id' => 1, 'project_id' => 1, 'buyer_id' => $buyer->id,
            'order' => '25001', 'status' => 'conciliacion', 'currency' => 'MXN', 'per_page' => 50,
        ]);
        $data = app(SupplierAccountStatementController::class)->index($request)->getData();
        $this->assertSame(3, $data['rows']->total());
        $this->assertSame(50, $data['rows']->perPage());
        foreach ($data['rows'] as $row) {
            $this->assertSame('conciliacion', $row['status']);
            $this->assertSame($order->id, $row['order_id']);
            $this->assertSame('MXN', $row['currency']);
        }
        $this->assertSame(40000, $data['summary']['MXN']['unreconciled']);
        $this->assertSame($before, $this->financialState());
    }

    private function financialState(): array
    {
        $state = [];
        foreach (['purchase_orders', 'purchase_order_milestones', 'payments', 'purchase_order_invoices', 'invoice_milestone'] as $table) {
            $state[$table] = DB::table($table)->get()->toJson();
        }
        return $state;
    }
}