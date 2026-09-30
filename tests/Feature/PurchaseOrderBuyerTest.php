<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Supplier;
use App\Models\PurchaseOrderInvoice;
use App\Exports\PurchaseOrderInvoiceExport;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderInvoiceController;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseOrderBuyerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable();
            $table->string('elaborated_by')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type')->default('materiales_servicios');
            $table->string('project')->nullable();
            $table->string('status')->default('pendiente');
            $table->string('delivery_status')->nullable();
            $table->decimal('amount', 14, 2)->default(100);
            $table->timestamp('archived_at')->nullable();
            foreach (['purchase_request_id', 'project_work_id', 'mobile_asset_id'] as $column) {
                $table->unsignedBigInteger($column)->nullable();
            }
            foreach (['currency', 'site', 'recurrence_type', 'recurrence_frequency', 'recurrence_start_date', 'recurrence_end_date'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['tax_rate', 'isr_rate', 'retention_iva_rate', 'retention_isr_rate', 'cedular_rate'] as $column) {
                $table->decimal($column, 14, 4)->nullable();
            }
            $table->boolean('is_destajo')->default(false);
            $table->json('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $migration = require database_path('migrations/2026_09_29_190000_add_buyer_id_to_purchase_orders_table.php');
        $migration->up();
        $permissions = require database_path('migrations/2026_04_23_225441_create_permission_tables.php');
        $permissions->up();
        foreach (['admin', 'Orden de compra', 'Pagos', 'Solmat', 'Recepción'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('rfc_name');
            $table->string('commercial_name')->nullable();
            foreach (Supplier::PROFILE_REQUIREMENTS as $requirement) {
                if ($requirement['source'] === 'supplier' && !in_array($requirement['column'], ['rfc_name', 'commercial_name'])) {
                    $table->string($requirement['column'])->nullable();
                }
            }
            $table->softDeletes();
        });
        foreach (['contact' => 'supplier_contacts', 'location' => 'supplier_locations'] as $source => $name) {
            Schema::create($name, function (Blueprint $table) use ($source) {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                foreach (Supplier::PROFILE_REQUIREMENTS as $requirement) {
                    if ($requirement['source'] === $source) {
                        $table->string($requirement['column'])->nullable();
                    }
                }
                if ($source === 'contact') {
                    $table->boolean('is_primary')->default(true);
                }
            });
        }
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status');
        });
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->date('delivery_due_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->unsignedBigInteger('purchase_request_item_id')->nullable();
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->string('description')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->timestamps();
        });
        Schema::create('purchase_order_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->date('due_date')->nullable();
            $table->decimal('value', 14, 2)->default(100);
            $table->decimal('covered_amount', 14, 2)->default(0);
            $table->string('concept')->nullable();
            $table->string('payment_condition')->default('credito');
            $table->string('value_type')->default('fijo');
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('milestone_id');
            $table->decimal('amount', 14, 2);
            $table->string('status');
        });
        Schema::create('purchase_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('status');
            $table->timestamp('attached_at')->nullable();
            $table->string('folio')->nullable();
            $table->date('due_date')->nullable();
        });
        Schema::create('invoice_milestone', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_invoice_id');
            $table->unsignedBigInteger('purchase_order_milestone_id');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('action_by');
            $table->string('model_action');
            $table->unsignedBigInteger('model_id');
            $table->string('type');
            $table->text('data');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });
        Schema::create('project_works', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('name');
        });
        foreach (['purchase_order_project_works' => 'purchase_order_id', 'purchase_request_project_works' => 'purchase_request_id'] as $name => $parentColumn) {
            Schema::create($name, function (Blueprint $table) use ($parentColumn) {
                $table->unsignedBigInteger($parentColumn);
                $table->unsignedBigInteger('project_work_id');
                $table->timestamps();
            });
        }
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('folio')->nullable();
            $table->string('status')->default('sent_to_purchasing');
            $table->json('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->string('description');
            $table->string('unit');
            $table->decimal('purchase_quantity', 14, 4);
        });
    }

    public function test_buyer_relation_and_scope_use_user_id_not_the_signature_text(): void
    {
        $buyer = User::create(['name' => 'Comprador A', 'email' => 'a@example.com', 'password' => 'password']);
        $other = User::create(['name' => 'Comprador B', 'email' => 'b@example.com', 'password' => 'password']);
        $owned = PurchaseOrder::create(['buyer_id' => $buyer->id, 'elaborated_by' => 'Firma histórica']);
        PurchaseOrder::create(['buyer_id' => $other->id, 'elaborated_by' => $buyer->name]);
        PurchaseOrder::create(['elaborated_by' => $buyer->name]);

        $this->assertSame([$owned->id], PurchaseOrder::ownedBy($buyer->id)->pluck('id')->all());
        $this->assertSame($buyer->id, $owned->buyer->id);
        $this->assertSame([$owned->id], $buyer->purchaseOrders->pluck('id')->all());

        $buyer->delete();

        $this->assertNull($owned->fresh()->buyer_id);
        $this->assertSame('Firma histórica', $owned->fresh()->elaborated_by);
        $this->assertSame(3, DB::table('purchase_orders')->count());
    }

    public function test_non_admin_scope_all_and_due_date_sorts_remain_owned_and_active(): void
    {
        $buyer = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $buyer->assignRole('Orden de compra');
        $this->actingAs($buyer);
        $owned = PurchaseOrder::create(['buyer_id' => $buyer->id, 'folio' => '25001']);
        $foreign = PurchaseOrder::create(['folio' => '25002', 'delivery_status' => 'entregado']);
        PurchaseOrder::create(['buyer_id' => $buyer->id, 'folio' => '25003', 'archived_at' => now()]);
        foreach ([$owned, $foreign] as $order) {
            DB::table('purchase_order_invoices')->insert(['purchase_order_id' => $order->id, 'status' => 'en_proceso']);
        }

        foreach (['', 'payment_asc', 'payment_desc', 'delivery_asc', 'delivery_desc'] as $sort) {
            $data = app(PurchaseOrderController::class)->index(Request::create('/ordenes-de-compra', 'GET', ['scope' => 'all', 'sort_due' => $sort]))->getData();
            $this->assertSame('mine', $data['scope']);
            $this->assertSame([$owned->id], $data['orders']->pluck('id')->all());
            $this->assertSame([$owned->id], $data['recentPendingInvoices']->pluck('purchase_order_id')->all());
        }
    }

    public function test_admin_can_switch_between_complete_and_owned_lists_and_find_unassigned_orders(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $owned = PurchaseOrder::create(['buyer_id' => $admin->id, 'folio' => '25001']);
        $legacy = PurchaseOrder::create(['folio' => '25002']);

        $all = app(PurchaseOrderController::class)->index(Request::create('/ordenes-de-compra'))->getData();
        $this->assertSame('all', $all['scope']);
        $this->assertSame(2, $all['orders']->total());
        $this->assertSame(2, $all['trayCounts']['all']);
        $mine = app(PurchaseOrderController::class)->index(Request::create('/ordenes-de-compra', 'GET', ['scope' => 'mine']))->getData();
        $this->assertSame([$owned->id], $mine['orders']->pluck('id')->all());
        $this->assertSame(1, $mine['trayCounts']['all']);
        $pending = app(PurchaseOrderController::class)->index(Request::create('/ordenes-de-compra', 'GET', ['buyer_status' => 'unassigned']))->getData();
        $this->assertSame([$legacy->id], $pending['orders']->pluck('id')->all());
    }

    public function test_archived_orders_remain_owned_for_non_admin(): void
    {
        $buyer = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $buyer->assignRole('Orden de compra');
        $this->actingAs($buyer);
        $owned = PurchaseOrder::create(['buyer_id' => $buyer->id, 'archived_at' => now()]);
        PurchaseOrder::create(['archived_at' => now()]);
        $data = app(PurchaseOrderController::class)->archived(Request::create('/ordenes-de-compra/archivadas', 'GET', ['scope' => 'all']))->getData();

        $this->assertSame('mine', $data['scope']);
        $this->assertSame([$owned->id], $data['orders']->pluck('id')->all());
    }

    public function test_invoice_filter_and_export_preserve_payments_and_collaborative_access(): void
    {
        $buyer = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $buyer->assignRole('Orden de compra');
        $owned = PurchaseOrder::create(['buyer_id' => $buyer->id]);
        $foreign = PurchaseOrder::create([]);
        foreach ([$owned, $foreign] as $order) {
            DB::table('purchase_order_invoices')->insert(['purchase_order_id' => $order->id, 'status' => 'en_proceso', 'attached_at' => '2026-09-01 12:00:00']);
        }
        $this->assertSame($buyer->id, $buyer->invoiceBuyerFilter());
        $this->assertSame([$owned->id], PurchaseOrderInvoice::ownedBy($buyer->id)->pluck('purchase_order_id')->all());
        $export = new PurchaseOrderInvoiceExport('2026-09-01', '2026-09-30', 'ambas', $buyer->invoiceBuyerFilter());
        $this->assertSame([$owned->id], $export->query()->pluck('purchase_order_id')->all());

        foreach (['Pagos', 'Solmat', 'Recepción', 'admin'] as $role) {
            $buyer->assignRole($role);
            $this->assertNull($buyer->invoiceBuyerFilter());
            $export = new PurchaseOrderInvoiceExport('2026-09-01', '2026-09-30', 'ambas', $buyer->invoiceBuyerFilter());
            $this->assertSame(2, $export->query()->count());
            $buyer->removeRole($role);
        }
    }

    public function test_buyer_field_is_readonly_and_uses_authenticated_user_id_even_after_validation_errors(): void
    {
        $buyer = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $this->actingAs($buyer);
        session()->flash('_old_input', ['buyer_id' => 999, 'elaborated_by' => 'Otra persona']);
        $html = view('purchase_orders.partials.buyer-field')->render();

        $this->assertStringContainsString('value="Comprador" readonly', $html);
        $this->assertStringContainsString('name="buyer_id" value="' . $buyer->id . '"', $html);
        $this->assertStringNotContainsString('Otra persona', $html);
        $this->assertStringNotContainsString('name="elaborated_by"', $html);
    }

    public function test_reassignment_preserves_signature_status_and_archived_history(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $buyer = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $this->actingAs($admin);
        $order = PurchaseOrder::create(['folio' => '25001', 'status' => 'autorizada', 'archived_at' => now(), 'elaborated_by' => 'Firma original']);
        app(PurchaseOrderController::class)->assignBuyer(Request::create('/comprador', 'PATCH', ['buyer_id' => $buyer->id]), $order);

        $this->assertSame($buyer->id, $order->fresh()->buyer_id);
        $this->assertSame('Firma original', $order->fresh()->elaborated_by);
        $this->assertSame('autorizada', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->archived_at);
        $this->assertSame(1, DB::table('notifications')->where('model_id', $order->id)->count());
    }

    public function test_backfill_is_exact_unique_idempotent_and_preserves_historical_dates(): void
    {
        $buyer = User::create(['name' => 'José Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $buyer->assignRole('Orden de compra');
        foreach (['dup-a', 'dup-b'] as $email) {
            User::create(['name' => 'Duplicado', 'email' => $email . '@example.com', 'password' => 'password'])->assignRole('Orden de compra');
        }
        User::create(['name' => 'Sin rol', 'email' => 'other@example.com', 'password' => 'password']);
        $orders = collect();
        foreach (['José Comprador', ' José Comprador ', 'Duplicado', 'Sin rol', 'jOSé Comprador', 'Jose Comprador', '', 'Desconocido'] as $name) {
            $orders->push(PurchaseOrder::create(['elaborated_by' => $name]));
        }
        DB::table('purchase_orders')->whereIn('id', $orders->pluck('id'))->update(['updated_at' => '2020-01-01 00:00:00']);
        $orders[1]->update(['archived_at' => now()]);
        $deleted = PurchaseOrder::create(['elaborated_by' => $buyer->name]);
        $deleted->delete();
        $alreadyAssigned = PurchaseOrder::create(['buyer_id' => $buyer->id, 'elaborated_by' => 'Desconocido']);

        $this->artisan('purchase-orders:backfill-buyers', ['--dry-run' => true, '--chunk' => 1])
            ->expectsOutputToContain('Por vincular: 3')
            ->expectsOutputToContain('Omitidas: 6')
            ->assertExitCode(0);
        $this->assertSame(1, PurchaseOrder::withTrashed()->whereNotNull('buyer_id')->count());

        $this->artisan('purchase-orders:backfill-buyers', ['--chunk' => 2])
            ->expectsOutputToContain('Vinculadas: 3')->assertExitCode(0);
        $this->assertSame($buyer->id, $orders[0]->fresh()->buyer_id);
        $this->assertSame('2020-01-01 00:00:00', $orders[0]->fresh()->updated_at->format('Y-m-d H:i:s'));
        $this->assertSame('José Comprador', $orders[0]->fresh()->elaborated_by);
        $this->assertSame($buyer->id, PurchaseOrder::withTrashed()->find($deleted->id)->buyer_id);
        $this->assertSame($buyer->id, $alreadyAssigned->fresh()->buyer_id);
        foreach ($orders->slice(2) as $order) {
            $this->assertNull($order->fresh()->buyer_id);
        }
        $this->artisan('purchase-orders:backfill-buyers')
            ->expectsOutputToContain('Vinculadas: 0')->assertExitCode(0);
    }

    public function test_normal_and_solcom_creation_assign_authenticated_buyer_and_ignore_submitted_identity(): void
    {
        $buyer = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $this->actingAs($buyer);
        $supplierData = [];
        $contactData = [];
        $locationData = [];
        foreach (Supplier::PROFILE_REQUIREMENTS as $requirement) {
            match ($requirement['source']) {
                'supplier' => $supplierData[$requirement['column']] = 'Completo',
                'contact' => $contactData[$requirement['column']] = 'Completo',
                'location' => $locationData[$requirement['column']] = 'Completo',
            };
        }
        $supplierId = DB::table('suppliers')->insertGetId($supplierData);
        DB::table('supplier_contacts')->insert($contactData + ['supplier_id' => $supplierId]);
        DB::table('supplier_locations')->insert($locationData + ['supplier_id' => $supplierId]);
        $projectId = DB::table('projects')->insertGetId(['name' => 'Proyecto', 'status' => 'active']);
        $workId = DB::table('project_works')->insertGetId(['project_id' => $projectId, 'name' => 'Obra']);
        $solcomId = DB::table('purchase_requests')->insertGetId(['project_id' => $projectId]);
        DB::table('purchase_request_project_works')->insert(['purchase_request_id' => $solcomId, 'project_work_id' => $workId]);
        $itemId = DB::table('purchase_request_items')->insertGetId(['purchase_request_id' => $solcomId, 'description' => 'Material', 'unit' => 'PZA', 'purchase_quantity' => 2]);
        $data = [
            'type' => 'materiales_servicios', 'supplier_id' => $supplierId, 'currency' => 'MXN',
            'tax_rate' => '16', 'project_id' => $projectId, 'project_work_ids' => [$workId],
            'buyer_id' => 99999, 'elaborated_by' => 'Persona ajena',
        ];

        foreach ([[], ['purchase_request_id' => $solcomId, 'selected_item_ids' => [$itemId]]] as $source) {
            app(PurchaseOrderController::class)->store(Request::create('/ordenes-de-compra', 'POST', $data + $source));
            $order = PurchaseOrder::latest('id')->first();
            $this->assertSame($buyer->id, $order->buyer_id);
            $this->assertSame($buyer->name, $order->elaborated_by);
            $this->assertSame('pendiente', $order->status);
            $this->assertSame([$workId], $order->projectWorks->pluck('id')->all());
            if ($source) {
                $this->assertSame($solcomId, $order->purchase_request_id);
                $this->assertSame(1, $order->items->count());
            }
        }
    }

    public function test_invoice_list_and_counts_follow_buyer_filter_but_payments_keeps_global_access(): void
    {
        $buyer = User::create(['name' => 'Comprador', 'email' => 'buyer@example.com', 'password' => 'password']);
        $buyer->assignRole('Orden de compra');
        $this->actingAs($buyer);
        $owned = PurchaseOrder::create(['buyer_id' => $buyer->id]);
        $foreign = PurchaseOrder::create([]);
        foreach ([$owned, $foreign] as $order) {
            DB::table('purchase_order_invoices')->insert(['purchase_order_id' => $order->id, 'status' => 'en_proceso', 'folio' => 'FACT-' . $order->id]);
        }

        $data = app(PurchaseOrderInvoiceController::class)->index(Request::create('/facturas'))->getData();
        $this->assertSame(1, $data['pendingCount']);
        $this->assertSame([$owned->id], $data['invoices']->pluck('purchase_order_id')->all());

        $buyer->assignRole('Pagos');
        $global = app(PurchaseOrderInvoiceController::class)->index(Request::create('/facturas'))->getData();
        $this->assertSame(2, $global['pendingCount']);
        $this->assertSame(2, $global['invoices']->total());
        $orders = app(PurchaseOrderController::class)->index(Request::create('/ordenes-de-compra', 'GET', ['scope' => 'all']))->getData();
        $this->assertSame('mine', $orders['scope']);
        $this->assertSame([$owned->id], $orders['orders']->pluck('id')->all());
    }
}