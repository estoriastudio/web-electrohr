<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;
use App\Models\PurchaseRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasRole('supplier_portal_access')) {
            return redirect()->route('supplier_portal.dashboard');
        }

        // Valores por defecto (se rellenan según el rol)
        $totalPendientePago             = 0.0;
        $totalPorAutorizar              = 0.0;
        $totalPagado                    = 0.0;
        $paymentTotalsByCurrency        = [
            'por_autorizar' => ['MXN' => 0.0, 'USD' => 0.0, 'EUR' => 0.0],
            'autorizado'    => ['MXN' => 0.0, 'USD' => 0.0, 'EUR' => 0.0],
            'pagado'        => ['MXN' => 0.0, 'USD' => 0.0, 'EUR' => 0.0],
        ];
        $chartLabels                    = [];
        $chartValues                    = [];
        $top5PorAutorizar               = collect();
        $urgencyLabels                  = [];
        $urgencyValues                  = [];
        $totalImporteHitosVencidos       = 0.0;
        $top5Urgencias                  = collect();
        $solcomSearch                   = null;
        $solcomPendientes               = collect();
        $ocsPendientesAutorizar         = collect();
        $ocsPendientesEntregarSitio     = collect();
        $ocsPendientesEntregarElectrohr = collect();
        $ocsVencidasEntrega             = collect();

        // ── Bloque pagos (admin + Pagos) ──────────────────────────────
        if ($user->hasAnyRole(['admin', 'Pagos'])) {
            $totalPendientePago = (float) Payment::where('status', 'autorizado')->sum('amount');
            $totalPorAutorizar  = (float) Payment::where('status', 'por_autorizar')->sum('amount');
            $totalPagado        = (float) Payment::where('status', 'pagado')->sum('amount');

            Payment::query()
                ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
                ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
                ->whereIn('payments.status', array_keys($paymentTotalsByCurrency))
                ->selectRaw('payments.status, purchase_orders.currency, SUM(payments.amount) AS total')
                ->groupBy('payments.status', 'purchase_orders.currency')
                ->get()
                ->each(function ($paymentTotal) use (&$paymentTotalsByCurrency): void {
                    $paymentTotalsByCurrency[$paymentTotal->status][$paymentTotal->currency] = (float) $paymentTotal->total;
                });

            $porAutorizarRows = Payment::join(
                    'purchase_order_milestones',
                    'payments.milestone_id', '=', 'purchase_order_milestones.id'
                )
                ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
                ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
                ->where('payments.status', 'por_autorizar')
                ->selectRaw('suppliers.rfc_name AS supplier_name, SUM(payments.amount) AS total')
                ->groupBy('suppliers.id', 'suppliers.rfc_name')
                ->orderByDesc('total')
                ->get();

            $chartLabels = $porAutorizarRows->pluck('supplier_name')->toArray();
            $chartValues = $porAutorizarRows->pluck('total')
                ->map(fn ($v) => (float) $v)
                ->toArray();

            $top5PorAutorizar = Payment::with(['milestone.purchaseOrder.supplier'])
                ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
                ->select('payments.*')
                ->where('payments.status', 'por_autorizar')
                ->orderByRaw('
                    CASE WHEN purchase_order_milestones.due_date IS NULL THEN 1 ELSE 0 END ASC,
                    purchase_order_milestones.due_date ASC
                ')
                ->limit(5)
                ->get();
        }

        // ── Bloque órdenes (admin + Orden de compra) ──────────────────
        if ($user->hasAnyRole(['admin', 'Orden de compra'])) {
            $urgencyData = [
                'Vencido'      => 0,
                'Esta semana'  => 0,
                'Este mes'     => 0,
                'Próximo mes'  => 0,
                'Futuro'       => 0,
            ];

            $today = Carbon::today();

            PurchaseOrderMilestone::whereNotNull('due_date')
                ->whereColumn('covered_amount', '<', 'value')
                ->get(['due_date', 'value', 'covered_amount'])
                ->each(function ($ms) use (&$urgencyData, &$totalImporteHitosVencidos, $today) {
                    $due = $ms->due_date;

                    if ($due->lt($today)) {
                        $urgencyData['Vencido']++;
                        $totalImporteHitosVencidos += max(0, (float) $ms->value - (float) $ms->covered_amount);
                    } elseif ($due->lte($today->copy()->addDays(7))) {
                        $urgencyData['Esta semana']++;
                    } elseif ($due->lte($today->copy()->addDays(30))) {
                        $urgencyData['Este mes']++;
                    } elseif ($due->lte($today->copy()->addDays(60))) {
                        $urgencyData['Próximo mes']++;
                    } else {
                        $urgencyData['Futuro']++;
                    }
                });

            $urgencyLabels = array_keys($urgencyData);
            $urgencyValues = array_values($urgencyData);

            $top5Urgencias = PurchaseOrderMilestone::with(['purchaseOrder.supplier'])
                ->whereNotNull('due_date')
                ->whereColumn('covered_amount', '<', 'value')
                ->orderBy('due_date')
                ->limit(5)
                ->get();

            // ── SOLCOM pendientes ──────────────────────────────────────────
            $solcomSearch = $request->get('solcom_search');

            $solcomPendientes = PurchaseRequest::whereIn('status', ['pending', 'changes_requested'])
                ->with(['project', 'materialRequest'])
                ->when($solcomSearch, fn ($q) => $q->where('folio', 'like', '%' . $solcomSearch . '%'))
                ->orderBy('created_at', 'desc')
                ->get();

            // ── OCs pendientes de autorizar ────────────────────────────────
            $ocsPendientesAutorizar = PurchaseOrder::whereIn('status', ['emitida', 'pendiente'])
                ->whereNull('archived_at')
                ->with('supplier')
                ->orderBy('created_at', 'desc')
                ->get();

            // ── OCs pendientes de entregar (autorizadas con delivery_date) ─
            $ocsPendientesEntregar = PurchaseOrder::where('status', 'autorizada')
                ->whereNull('archived_at')
                ->with(['supplier', 'purchaseRequest.materialRequest', 'items'])
                ->whereHas('items', fn ($q) => $q->whereNotNull('delivery_date'))
                ->get();

            $ocsPendientesEntregarSitio = $ocsPendientesEntregar
                ->filter(fn ($oc) => ($oc->purchaseRequest?->materialRequest?->location_type ?? 'sitio') !== 'electrohr')
                ->values();

            $ocsPendientesEntregarElectrohr = $ocsPendientesEntregar
                ->filter(fn ($oc) => ($oc->purchaseRequest?->materialRequest?->location_type ?? 'sitio') === 'electrohr')
                ->values();

            // ── OCs vencidas en tiempos de entrega ─────────────────────────
            $ocsVencidasEntrega = PurchaseOrder::where('status', 'autorizada')
                ->whereNull('archived_at')
                ->with(['supplier', 'purchaseRequest.assignedTo', 'purchaseRequest.materialRequest', 'items'])
                ->whereHas('items', function ($q) {
                    $q->whereNotNull('delivery_date')
                      ->whereRaw("delivery_date < CURDATE()");
                })
                ->get();
        }

        return view('index', compact(
            'totalPendientePago', 'totalPorAutorizar', 'totalPagado', 'paymentTotalsByCurrency',
            'chartLabels', 'chartValues', 'top5PorAutorizar',
            'urgencyLabels', 'urgencyValues', 'totalImporteHitosVencidos', 'top5Urgencias',
            'solcomSearch', 'solcomPendientes',
            'ocsPendientesAutorizar',
            'ocsPendientesEntregarSitio', 'ocsPendientesEntregarElectrohr',
            'ocsVencidasEntrega'
        ));
    }

    public function users(): View
    {
        return view('users.index');
    }

    public function settings(): View
    {
        return view('settings');
    }
}
