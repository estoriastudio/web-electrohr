<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentMultipleMilestoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_payments_user_can_split_the_initial_payment_into_two_payments(): void
    {
        $user = $this->paymentsUser();
        $milestone = $this->milestoneWithInitialPayment();

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'existing_payment_amount' => 600,
                'amount' => 400,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder));

        $this->assertDatabaseHas('payments', [
            'id' => $milestone->payments()->first()->id,
            'amount' => 600,
            'status' => 'por_autorizar',
        ]);
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 400,
            'payment_date' => '2026-08-30',
            'status' => 'por_autorizar',
        ]);
        $this->assertSame(2, $milestone->payments()->count());
    }

    public function test_initial_payment_split_can_leave_available_balance_for_later_payments(): void
    {
        $milestone = $this->milestoneWithInitialPayment();

        $this->actingAs($this->paymentsUser())
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'existing_payment_amount' => 400,
                'amount' => 300,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder));

        $this->assertSame(2, $milestone->payments()->count());
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 400,
        ]);

        $this->actingAs($this->paymentsUser())
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'amount' => 300,
                'payment_date' => '2026-09-15',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder));

        $this->assertSame(3, $milestone->payments()->count());
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 300,
            'payment_date' => '2026-09-15',
        ]);
    }

    public function test_initial_payment_split_cannot_exceed_the_milestone_total(): void
    {
        $milestone = $this->milestoneWithInitialPayment();

        $this->actingAs($this->paymentsUser())
            ->from(route('purchase_orders.show', $milestone->purchaseOrder))
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'existing_payment_amount' => 700,
                'amount' => 400,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder))
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, $milestone->payments()->count());
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 1000,
        ]);
    }

    public function test_rejected_payments_do_not_consume_available_milestone_balance(): void
    {
        $milestone = $this->milestoneWithInitialPayment();
        $milestone->payments()->first()->update(['status' => 'rechazado']);

        $this->actingAs($this->paymentsUser())
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'amount' => 1000,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder));

        $this->assertSame(2, $milestone->payments()->count());
        $this->assertDatabaseHas('payments', [
            'milestone_id' => $milestone->id,
            'amount' => 1000,
            'status' => 'por_autorizar',
        ]);
    }

    public function test_cannot_add_payment_when_the_initial_payment_is_already_authorized(): void
    {
        $milestone = $this->milestoneWithInitialPayment();
        $milestone->payments()->first()->update(['status' => 'autorizado']);

        $this->actingAs($this->paymentsUser())
            ->from(route('purchase_orders.show', $milestone->purchaseOrder))
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id,
                'amount' => 100,
                'payment_date' => '2026-08-30',
            ])
            ->assertRedirect(route('purchase_orders.show', $milestone->purchaseOrder))
            ->assertSessionHasErrors('milestone_id');

        $this->assertSame(1, $milestone->payments()->count());
    }

    public function test_payments_user_can_mark_multiple_authorized_payments_as_paid_with_one_spei(): void
    {
        Storage::fake('s3');

        $firstMilestone = $this->milestoneWithInitialPayment();
        $firstPayment = $firstMilestone->payments()->first();
        $firstPayment->update(['status' => 'autorizado']);

        $secondMilestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $firstMilestone->purchase_order_id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 300,
            'covered_amount' => 0,
            'due_date' => '2026-09-15',
        ]);
        $secondPayment = Payment::create([
            'milestone_id' => $secondMilestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 300,
            'payment_date' => '2026-09-15',
            'status' => 'autorizado',
        ]);

        $this->actingAs($this->paymentsUser())
            ->post(route('payments.mark_multiple_paid_with_spei'), [
                'payment_ids' => [$firstPayment->id, $secondPayment->id],
                'spei_receipt_file' => UploadedFile::fake()->create('spei.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('payments.payable'));

        $firstPayment->refresh();
        $secondPayment->refresh();

        $this->assertSame('pagado', $firstPayment->status);
        $this->assertSame('pagado', $secondPayment->status);
        $this->assertNotNull($firstPayment->spei_receipt_path);
        $this->assertSame($firstPayment->spei_receipt_path, $secondPayment->spei_receipt_path);
        $this->assertSame($firstPayment->spei_receipt_name, $secondPayment->spei_receipt_name);
        Storage::disk('s3')->assertExists($firstPayment->spei_receipt_path);
        $this->assertSame(1000.0, (float) $firstMilestone->fresh()->covered_amount);
        $this->assertSame(300.0, (float) $secondMilestone->fresh()->covered_amount);
    }

    public function test_payments_user_can_persist_and_clear_payable_selection_in_session(): void
    {
        $milestone = $this->milestoneWithInitialPayment();
        $payment = $milestone->payments()->first();
        $payment->update(['status' => 'autorizado']);
        $user = $this->paymentsUser();

        $this->actingAs($user)
            ->postJson(route('payments.payable.selection.sync'), [
                'selected_ids' => [$payment->id],
            ])
            ->assertOk()
            ->assertJson([
                'selected_ids' => [$payment->id],
                'selected_count' => 1,
            ])
            ->assertSessionHas('payments.payable.selection.selected_ids', [$payment->id]);

        $this->get(route('payments.payable'))
            ->assertOk()
            ->assertViewHas('selectionState', function (array $selectionState) use ($payment) {
                return $selectionState['selected_ids'] === [$payment->id]
                    && $selectionState['selected_count'] === 1;
            });

        $this->postJson(route('payments.payable.selection.clear'))
            ->assertOk()
            ->assertJson([
                'selected_ids' => [],
                'selected_count' => 0,
            ])
            ->assertSessionMissing('payments.payable.selection');
    }

    public function test_admin_can_persist_and_authorize_multiple_pending_payments(): void
    {
        $firstMilestone = $this->milestoneWithInitialPayment();
        $firstPayment = $firstMilestone->payments()->first();
        $secondPayment = Payment::create([
            'milestone_id' => $firstMilestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 1,
            'payment_date' => '2026-09-15',
            'status' => 'pospuesto',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->postJson(route('payments.authorization.selection.sync'), [
                'selected_ids' => [$firstPayment->id, $secondPayment->id],
            ])
            ->assertOk()
            ->assertJson([
                'selected_ids' => [$firstPayment->id, $secondPayment->id],
                'selected_count' => 2,
            ])
            ->assertSessionHas('payments.authorization.selection.selected_ids', [$firstPayment->id, $secondPayment->id]);

        $this->get(route('payments.index'))
            ->assertOk()
            ->assertViewHas('selectionState', function (array $selectionState) use ($firstPayment, $secondPayment) {
                return $selectionState['selected_ids'] === [$firstPayment->id, $secondPayment->id]
                    && $selectionState['selected_count'] === 2;
            });

        $this->post(route('payments.authorize_multiple'), [
            'payment_ids' => [$firstPayment->id, $secondPayment->id],
        ])
            ->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', ['id' => $firstPayment->id, 'status' => 'autorizado']);
        $this->assertDatabaseHas('payments', ['id' => $secondPayment->id, 'status' => 'autorizado']);
        $this->assertSessionMissing('payments.authorization.selection');
    }

    public function test_dashboard_separates_payment_indicators_by_currency(): void
    {
        $mxnMilestone = $this->milestoneWithInitialPayment('MXN');
        $usdMilestone = $this->milestoneWithInitialPayment('USD');
        $mxnMilestone->payments()->first()->update(['status' => 'autorizado']);
        $usdMilestone->payments()->first()->update(['status' => 'pagado']);

        $this->actingAs($this->paymentsUser())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('paymentTotalsByCurrency', function (array $totals): bool {
                return $totals['autorizado']['MXN'] === 1000.0
                    && $totals['pagado']['USD'] === 1000.0
                    && $totals['pagado']['MXN'] === 0.0;
            });
    }

    public function test_paid_payments_can_be_filtered_by_currency(): void
    {
        $mxnMilestone = $this->milestoneWithInitialPayment('MXN');
        $usdMilestone = $this->milestoneWithInitialPayment('USD');
        $mxnPayment = $mxnMilestone->payments()->first();
        $usdPayment = $usdMilestone->payments()->first();
        $mxnPayment->update(['status' => 'pagado']);
        $usdPayment->update(['status' => 'pagado']);

        $this->actingAs($this->paymentsUser())
            ->get(route('payments.paid', ['currency' => 'USD']))
            ->assertOk()
            ->assertViewHas('currency', 'USD')
            ->assertViewHas('payments', function ($payments) use ($usdPayment): bool {
                return $payments->count() === 1 && $payments->first()->is($usdPayment);
            });
    }

    private function paymentsUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Pagos');

        return $user;
    }

    private function milestoneWithInitialPayment(string $currency = 'MXN'): PurchaseOrderMilestone
    {
        $supplier = Supplier::create(['rfc_name' => 'Proveedor de prueba']);
        $purchaseOrder = PurchaseOrder::create([
            'folio' => random_int(25000, 99999),
            'type' => 'materiales_servicios',
            'supplier_id' => $supplier->id,
            'currency' => $currency,
            'amount' => 1000,
            'status' => 'autorizada',
            'recurrence_type' => 'unico',
        ]);
        $milestone = PurchaseOrderMilestone::create([
            'purchase_order_id' => $purchaseOrder->id,
            'type' => 'regular',
            'payment_condition' => 'credito',
            'value_type' => 'fijo',
            'value' => 1000,
            'covered_amount' => 0,
            'due_date' => '2026-08-30',
        ]);

        Payment::create([
            'milestone_id' => $milestone->id,
            'folio' => 'PAY-' . random_int(10000, 99999),
            'amount' => 1000,
            'payment_date' => '2026-08-30',
            'status' => 'por_autorizar',
        ]);

        return $milestone->fresh('purchaseOrder');
    }
}