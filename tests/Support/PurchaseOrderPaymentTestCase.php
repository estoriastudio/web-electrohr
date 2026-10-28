<?php

namespace Tests\Support;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class PurchaseOrderPaymentTestCase extends TestCase
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
        (require database_path('migrations/2026_04_23_225441_create_permission_tables.php'))->up();
        foreach (['admin', 'Orden de compra', 'Pagos'] as $name) {
            Role::create(['name' => $name, 'guard_name' => 'web']);
        }
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable();
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->string('status')->default('autorizada');
            $table->string('currency')->default('MXN');
            $table->boolean('is_destajo')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 4);
        });
        Schema::create('purchase_order_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('type')->default('regular');
            $table->string('concept')->nullable();
            $table->string('payment_condition')->default('credito');
            $table->boolean('is_advance')->default(false);
            $table->string('value_type')->default('fijo');
            $table->decimal('value', 15, 2)->default(1000);
            $table->decimal('covered_amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->date('invoice_date')->nullable();
            $table->timestamp('delivery_confirmed_at')->nullable();
            $table->string('credit_start')->nullable();
            $table->unsignedSmallInteger('credit_days')->nullable();
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('milestone_id');
            $table->string('folio');
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('por_autorizar');
            $table->date('payment_date');
            $table->date('invoice_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->timestamp('checklist_verified_at')->nullable();
            $table->unsignedBigInteger('regularization_invoice_id')->nullable();
            $table->timestamp('regularized_at')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('folio')->nullable();
            $table->string('status')->default('en_proceso');
            $table->decimal('amount', 15, 2);
            $table->decimal('net_scope', 15, 2)->nullable();
            $table->string('currency')->default('MXN');
            foreach (['file_name', 'file_path', 'xml_file_name', 'xml_file_path'] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('issue_date')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('invoice_milestone', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_invoice_id');
            $table->unsignedBigInteger('purchase_order_milestone_id');
            $table->primary(['purchase_order_invoice_id', 'purchase_order_milestone_id']);
        });
        (require database_path('migrations/2026_10_27_000000_add_milestone_amount_adjustments.php'))->up();
        $this->mock(NotificationService::class)
            ->shouldReceive('send')->andReturn(new Notification);
    }

    protected function user(string $role = 'admin'): User
    {
        $user = User::create(['name' => 'Usuario de prueba', 'email' => uniqid().'@example.test', 'password' => 'password']);
        $user->assignRole($role);

        return $user;
    }

    protected function milestone(array $attributes = [], ?User $buyer = null): PurchaseOrderMilestone
    {
        $order = PurchaseOrder::create(['buyer_id' => $buyer?->id, 'status' => 'autorizada', 'currency' => 'MXN']);
        DB::table('purchase_order_items')->insert(['purchase_order_id' => $order->id, 'quantity' => 1, 'unit_price' => 1000]);

        return $order->milestones()->create($attributes + [
            'type' => 'regular', 'concept' => 'Entrega original', 'payment_condition' => 'credito',
            'credit_start' => 'invoice_validation', 'credit_days' => 30,
            'value_type' => 'fijo', 'value' => 1000, 'due_date' => '2027-01-30',
        ]);
    }

    protected function payment(PurchaseOrderMilestone $milestone, float $amount, string $status = 'por_autorizar'): Payment
    {
        return $milestone->payments()->create([
            'folio' => uniqid('PAY-'), 'amount' => $amount, 'status' => $status, 'payment_date' => '2027-01-30',
        ]);
    }

    protected function invoice(PurchaseOrderMilestone $milestone, float $amount = 500, string $status = 'en_proceso'): PurchaseOrderInvoice
    {
        return PurchaseOrderInvoice::create([
            'purchase_order_id' => $milestone->purchase_order_id, 'amount' => $amount, 'currency' => 'MXN', 'status' => $status,
        ]);
    }
}
