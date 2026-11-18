<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderInvoice;
use App\Models\PurchaseOrderMilestone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Fase 4: seguimiento del evento hasta su cierre (vista `event_followups`). Un hito con pagos hechos sigue
 * abierto mientras falte la entrega validada (salvo anticipos) o la factura aceptada. Compras anota el
 * seguimiento de sus OC; Dirección y Pagos consultan.
 */
class EventFollowupController extends Controller
{
    public function index(Request $request): View
    {
        $missing = in_array($request->input('missing'), ['delivery', 'invoice'], true) ? $request->input('missing') : 'all';

        $milestones = $this->openEvents()
            ->when($missing === 'delivery', fn ($q) => $q->where('is_advance', false)->whereNull('delivery_confirmed_at'))
            ->when($missing === 'invoice', fn ($q) => $q->whereDoesntHave('invoices', fn ($i) => $i->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA)))
            ->with(['purchaseOrder.supplier', 'purchaseOrder.buyer', 'payments', 'invoices', 'followupUpdatedBy'])
            ->orderByRaw('followup_due_date IS NULL, followup_due_date')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('event_followups.index', compact('milestones', 'missing'));
    }

    public function update(Request $request, PurchaseOrderMilestone $milestone): RedirectResponse
    {
        abort_unless($milestone->purchaseOrder, 404);
        $this->authorizeAccess($milestone->purchaseOrder, 'purchase_orders');
        $data = $request->validate([
            'followup_note' => ['required', 'string', 'max:2000'],
            'followup_due_date' => ['nullable', 'date'],
        ]);

        $milestone->update([
            ...$data,
            'followup_updated_by' => Auth::id(),
            'followup_updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Seguimiento actualizado.');
    }

    /** Hitos pagados con algo pendiente, de las OC que el usuario ve (Compras: las suyas). */
    private function openEvents(): Builder
    {
        return PurchaseOrderMilestone::query()
            ->whereHas('payments', fn ($payments) => $payments->where('status', 'pagado'))
            ->whereHas('purchaseOrder', fn ($orders) => $orders->whereNull('archived_at')->visibleTo(Auth::user(), 'purchase_orders'))
            ->where(function (Builder $open) {
                $open->where(fn ($delivery) => $delivery->where('is_advance', false)->whereNull('delivery_confirmed_at'))
                    ->orWhereDoesntHave('invoices', fn ($i) => $i->where('status', PurchaseOrderInvoice::STATUS_ACEPTADA));
            });
    }
}
