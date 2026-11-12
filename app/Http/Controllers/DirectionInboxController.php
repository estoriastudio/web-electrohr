<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PayrollPeriod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderChangeRequest;
use App\Models\PurchaseRequestChangeNote;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Fase 3: espacio propio de Dirección. Reúne lo que espera su autorización.
 * Autoriza pagos (en Pagos: Autorizaciones) y OC con sus cambios y cancelaciones (desde la OC).
 */
class DirectionInboxController extends Controller
{
    public function index(): View
    {
        $paymentsQuery = Payment::query()
            ->whereIn('status', ['por_autorizar', 'pospuesto'])
            ->whereHas('milestone.purchaseOrder', fn ($orders) => $orders->where('status', 'autorizada'));

        $payments = (clone $paymentsQuery)
            ->with(['milestone.purchaseOrder.supplier'])
            ->orderBy('payment_date')
            ->limit(10)
            ->get();

        $ordersQuery = PurchaseOrder::query()->whereNull('archived_at')->where('status', 'emitida');
        $orders = (clone $ordersQuery)->with('supplier', 'buyer')->orderBy('created_at')->limit(10)->get();

        $orderChangeRequests = PurchaseOrderChangeRequest::query()
            ->where('status', 'pending')
            ->with(['purchaseOrder.supplier', 'requestedBy'])
            ->oldest()
            ->get();

        // Ajustes comerciales de SOLCOM que esperan a Dirección (los demás cambios no pasan por ella).
        $changesQuery = PurchaseRequestChangeNote::query()
            ->where('is_commercial', true)
            ->where('direction_status', 'pending')
            ->whereHas('purchaseRequest', fn ($requests) => $requests->whereNull('archived_at'));
        $changes = (clone $changesQuery)->with('purchaseRequest', 'requestedBy')->oldest()->get();

        // Algunas bases no tienen las tablas de nómina aunque sus migraciones figuren como corridas.
        $payrollAvailable = Schema::hasTable('payroll_periods');
        $payrollPeriods = ! $payrollAvailable ? collect() : PayrollPeriod::query()
            ->whereIn('status', ['closed', 'authorized'])
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->limit(10)
            ->get();

        return view('direction.inbox', [
            'payments' => $payments,
            'paymentsCount' => $paymentsQuery->count(),
            'orders' => $orders,
            'ordersCount' => $ordersQuery->count(),
            'orderChangeRequests' => $orderChangeRequests,
            'changes' => $changes,
            'changesCount' => $changesQuery->count(),
            'payrollPeriods' => $payrollPeriods,
            'payrollAvailable' => $payrollAvailable,
        ]);
    }
}
