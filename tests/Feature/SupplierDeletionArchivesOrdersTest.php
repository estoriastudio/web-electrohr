<?php

namespace Tests\Feature;

use App\Http\Controllers\SupplierController;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupplierDeletionArchivesOrdersTest extends TestCase
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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('rfc_name');
            $table->string('commercial_name')->nullable();
            $table->unsignedBigInteger('portal_user_id')->nullable();
            $table->boolean('portal_access_enabled')->default(false);
            $table->timestamp('portal_access_activated_at')->nullable();
            $table->timestamp('portal_access_deactivated_at')->nullable();
            $table->unsignedBigInteger('portal_access_managed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('action_by');
            $table->string('model_action');
            $table->string('model_id');
            $table->string('type');
            $table->text('data');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });
    }

    public function test_deleting_a_supplier_archives_its_orders_and_audits_each_movement(): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
        $this->actingAs($user);

        $supplier = Supplier::create(['rfc_name' => 'Proveedor SA']);
        $first = PurchaseOrder::create(['folio' => '100', 'supplier_id' => $supplier->id]);
        $second = PurchaseOrder::create(['folio' => '101', 'supplier_id' => $supplier->id]);
        $alreadyArchived = PurchaseOrder::create(['folio' => '102', 'supplier_id' => $supplier->id, 'archived_at' => now()->subDay()]);

        app(SupplierController::class)->destroy($supplier);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
        $this->assertNotNull($first->fresh()->archived_at);
        $this->assertNotNull($second->fresh()->archived_at);
        $this->assertSame(
            $alreadyArchived->archived_at->timestamp,
            $alreadyArchived->fresh()->archived_at->timestamp,
        );

        $this->assertSame(2, Notification::where('type', 'PurchaseOrder')->where('model_action', 'archive')->count());
        $audit = Notification::where('type', 'Supplier')->where('model_action', 'destroy')->sole();
        $this->assertStringContainsString('#100', $audit->data);
        $this->assertStringContainsString('#101', $audit->data);
        $this->assertStringNotContainsString('#102', $audit->data);

        $this->assertSame('Proveedor SA', $first->fresh()->supplier->rfc_name);
    }

    public function test_hard_deleting_a_supplier_with_orders_is_rejected(): void
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor SA']);
        PurchaseOrder::create(['folio' => '100', 'supplier_id' => $supplier->id]);

        $this->expectException(QueryException::class);

        $supplier->forceDelete();
    }
}
