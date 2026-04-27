<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PurchaseOrderMilestone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $user = Auth::user();

        // Valores por defecto (se rellenan según el rol)
        $totalPendientePago = 0.0;
        $totalPorAutorizar  = 0.0;
        $chartLabels        = [];
        $chartValues        = [];
        $top5PorAutorizar   = collect();
        $urgencyLabels      = [];
        $urgencyValues      = [];
        $top5Urgencias      = collect();

        // ── Bloque pagos (admin + payments) ───────────────────────────
        if ($user->hasAnyRole(['admin', 'payments'])) {
            $totalPendientePago = (float) Payment::where('status', 'autorizado')->sum('amount');
            $totalPorAutorizar  = (float) Payment::where('status', 'por_autorizar')->sum('amount');

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

        // ── Bloque órdenes (admin + orders) ───────────────────────────
        if ($user->hasAnyRole(['admin', 'orders'])) {
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
                ->get(['due_date'])
                ->each(function ($ms) use (&$urgencyData, $today) {
                    $due = $ms->due_date;

                    if ($due->lt($today)) {
                        $urgencyData['Vencido']++;
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
        }

        return view('index', compact(
            'totalPendientePago', 'totalPorAutorizar',
            'chartLabels', 'chartValues', 'top5PorAutorizar',
            'urgencyLabels', 'urgencyValues', 'top5Urgencias'
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
