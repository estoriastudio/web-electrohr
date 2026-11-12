<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderChangeRequest;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fase 4: cambio o cancelación de una OC autorizada. Compras (comprador de la OC) solicita con
 * motivo; Dirección o admin autoriza o rechaza. Un cambio autorizado regresa la OC a Emitida
 * para editarla y volver a autorizarla; una cancelación autorizada la cancela.
 */
class PurchaseOrderChangeRequestController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(PurchaseOrderChangeRequest::TYPES)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $this->authorizeAccess($purchaseOrder, 'purchase_orders', $data['type'] === PurchaseOrderChangeRequest::TYPE_CANCEL ? 'cancel' : 'update');
        $purchaseOrder->ensureOpen();

        if ($purchaseOrder->status !== 'autorizada') {
            return back()->with('error', 'Solo se pide cambio o cancelación de una OC autorizada; las demás se editan directamente.');
        }

        if ($purchaseOrder->pendingChangeRequest()->exists()) {
            return back()->with('error', 'Ya hay una solicitud pendiente para esta OC.');
        }

        $changeRequest = $purchaseOrder->changeRequests()->create([
            'type' => $data['type'],
            'reason' => $data['reason'],
            'requested_by' => Auth::id(),
        ]);

        $this->notify($purchaseOrder, 'solicitó '.mb_strtolower($changeRequest->typeLabel()).' de la OC #'.$this->folio($purchaseOrder).'. Motivo: '.$data['reason']);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Solicitud enviada a Dirección.');
    }

    public function approve(Request $request, PurchaseOrderChangeRequest $changeRequest): RedirectResponse
    {
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:2000']]);
        $purchaseOrder = $changeRequest->purchaseOrder;
        $this->ensurePending($changeRequest);

        DB::transaction(function () use ($changeRequest, $purchaseOrder, $data) {
            $changeRequest->update([
                'status' => 'approved',
                'decided_by' => Auth::id(),
                'decided_at' => now(),
                'decision_note' => $data['decision_note'] ?? null,
            ]);

            if ($changeRequest->type === PurchaseOrderChangeRequest::TYPE_CANCEL) {
                $purchaseOrder->update(['deletion_comment' => 'Cancelación autorizada por Dirección: '.$changeRequest->reason]);
                $purchaseOrder->delete();
            } else {
                $purchaseOrder->update(['status' => 'emitida', 'authorized_at' => null, 'authorized_by' => null]);
            }
        });

        $isCancel = $changeRequest->type === PurchaseOrderChangeRequest::TYPE_CANCEL;
        $this->notify($purchaseOrder, ($isCancel ? 'autorizó la cancelación' : 'autorizó el cambio').' de la OC #'.$this->folio($purchaseOrder).'.');

        return $isCancel
            ? redirect()->route('direction.inbox')->with('success', 'OC #'.$this->folio($purchaseOrder).' cancelada.')
            : redirect()->route('purchase_orders.show', $purchaseOrder)->with('success', 'Cambio autorizado: la OC regresó a Emitida para editarse y volver a autorizarse.');
    }

    public function reject(Request $request, PurchaseOrderChangeRequest $changeRequest): RedirectResponse
    {
        $data = $request->validate(['decision_note' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->ensurePending($changeRequest);

        $changeRequest->update([
            'status' => 'rejected',
            'decided_by' => Auth::id(),
            'decided_at' => now(),
            'decision_note' => $data['decision_note'],
        ]);

        $purchaseOrder = $changeRequest->purchaseOrder;
        $this->notify($purchaseOrder, 'rechazó la solicitud de '.mb_strtolower($changeRequest->typeLabel()).' de la OC #'.$this->folio($purchaseOrder).'. Motivo: '.$data['decision_note']);

        return redirect()->route('purchase_orders.show', $purchaseOrder)->with('success', 'Solicitud rechazada.');
    }

    private function ensurePending(PurchaseOrderChangeRequest $changeRequest): void
    {
        if (! $changeRequest->isPending()) {
            throw ValidationException::withMessages(['change_request' => 'La solicitud ya fue resuelta.']);
        }
    }

    private function folio(PurchaseOrder $purchaseOrder): string
    {
        return (string) ($purchaseOrder->folio ?? $purchaseOrder->id);
    }

    private function notify(PurchaseOrder $purchaseOrder, string $text): void
    {
        $this->notification->send([
            'type' => 'PurchaseOrder',
            'action_by' => Auth::id(),
            'model_action' => 'update',
            'model_id' => $purchaseOrder->id,
            'data' => $text,
        ]);
    }
}
