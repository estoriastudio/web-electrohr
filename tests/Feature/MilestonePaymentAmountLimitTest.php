<?php

namespace Tests\Feature;

use App\Models\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PurchaseOrderPaymentTestCase;

class MilestonePaymentAmountLimitTest extends PurchaseOrderPaymentTestCase
{
    public static function committedStatuses(): array
    {
        return array_map(fn ($status) => [$status], Payment::COMMITTED_STATUSES);
    }

    #[DataProvider('committedStatuses')]
    public function test_all_non_rejected_statuses_consume_balance(string $status): void
    {
        $milestone = $this->milestone();
        $this->payment($milestone, 600, $status);
        $this->payment($milestone, 100, 'por_autorizar');
        $this->actingAs($this->user('Pagos'))
            ->post(route('payments.store'), ['milestone_id' => $milestone->id, 'amount' => '300.01', 'payment_date' => '2027-02-01'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(2, $milestone->payments()->count());
        $this->assertEquals(700, $milestone->payments()->sum('amount'));

        $this->post(route('payments.store'), ['milestone_id' => $milestone->id, 'amount' => '300.00', 'payment_date' => '2027-02-01'])
            ->assertSessionHasNoErrors();
        $this->assertEquals(1000, $milestone->payments()->sum('amount'));
    }

    public function test_initial_split_is_atomic_and_can_fill_exact_balance(): void
    {
        $milestone = $this->milestone();
        $payment = $this->payment($milestone, 1000);
        $this->actingAs($this->user('Pagos'))
            ->post(route('payments.store'), [
                'milestone_id' => $milestone->id, 'existing_payment_amount' => 600, 'amount' => 400.01, 'payment_date' => '2027-02-01',
            ])->assertSessionHasErrors('amount');
        $this->assertSame('1000.00', $payment->fresh()->amount);
        $this->post(route('payments.store'), [
            'milestone_id' => $milestone->id, 'existing_payment_amount' => 600, 'amount' => 400, 'payment_date' => '2027-02-01',
        ])->assertSessionHasNoErrors();
        $this->assertEquals(1000, $milestone->payments()->sum('amount'));
    }

    public function test_percentage_milestone_uses_its_effective_amount(): void
    {
        $milestone = $this->milestone(['value_type' => 'porcentaje', 'value' => 25]);
        $this->payment($milestone, 200, 'autorizado');
        $this->actingAs($this->user('Pagos'))->post(route('payments.store'), [
            'milestone_id' => $milestone->id, 'amount' => 50.01, 'payment_date' => '2027-02-01',
        ])->assertSessionHasErrors('amount');
        $this->post(route('payments.store'), [
            'milestone_id' => $milestone->id, 'amount' => 50, 'payment_date' => '2027-02-01',
        ])->assertSessionHasNoErrors();
        $this->assertEquals(250, $milestone->payments()->sum('amount'));
    }

    public static function reactivationRoutes(): array
    {
        return [['payments.request_reactivation'], ['payments.update']];
    }

    #[DataProvider('reactivationRoutes')]
    public function test_reactivation_rechecks_balance_and_is_atomic(string $route): void
    {
        $milestone = $this->milestone();
        $rejected = $this->payment($milestone, 400, 'rechazado');
        $active = $this->payment($milestone, 600.01, 'autorizado');
        $this->actingAs($this->user())->patch(route($route, $rejected), [
            'reason' => 'Solicitar nueva autorización', 'status' => 'por_autorizar',
        ])->assertSessionHasErrors('status');
        $this->assertSame('rechazado', $rejected->fresh()->status);
        $active->update(['amount' => 600]);
        $this->patch(route($route, $rejected), [
            'reason' => 'Solicitar nueva autorización', 'status' => 'por_autorizar',
        ])->assertSessionHasNoErrors();
        $this->assertSame('por_autorizar', $rejected->fresh()->status);
    }

    public function test_rejected_payments_do_not_prevent_replacement(): void
    {
        $milestone = $this->milestone();
        $this->payment($milestone, 1000, 'rechazado');
        $this->actingAs($this->user('Pagos'))->post(route('payments.store'), [
            'milestone_id' => $milestone->id, 'amount' => 1000, 'payment_date' => '2027-02-01',
        ])->assertSessionHasNoErrors();
        $this->assertEquals(1000, $milestone->payments()->whereIn('status', Payment::COMMITTED_STATUSES)->sum('amount'));
    }

    public function test_swipe_cannot_reactivate_rejected_payment_without_balance(): void
    {
        $milestone = $this->milestone();
        $rejected = $this->payment($milestone, 400, 'rechazado');
        $active = $this->payment($milestone, 600.01, 'autorizado');
        $this->actingAs($this->user())->postJson(route('payments.swipe', $rejected), ['status' => 'por_autorizar'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('rechazado', $rejected->fresh()->status);
        $active->update(['amount' => 600]);
        $this->postJson(route('payments.swipe', $rejected), ['status' => 'por_autorizar'])->assertOk();
        $this->assertSame('por_autorizar', $rejected->fresh()->status);
    }
}
