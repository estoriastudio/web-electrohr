<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvidence;
use App\Models\PurchaseOrderMilestoneFulfillment;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderEvidenceController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'purchase_order_milestone_id' => 'nullable|exists:purchase_order_milestones,id',
            'description' => 'nullable|string|max:255',
            'evidence_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $purchaseOrder = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        $milestoneId = $validated['purchase_order_milestone_id'] ?? null;
        if ($milestoneId) {
            $isValidMilestone = $purchaseOrder->milestones()->whereKey($milestoneId)->exists();
            if (!$isValidMilestone) {
                return redirect()->route('purchase_orders.show', $purchaseOrder)
                    ->with('error', 'El hito seleccionado no pertenece a esta orden de compra.');
            }
        }

        $file = $request->file('evidence_file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getClientMimeType();

        $nextNumber = $purchaseOrder->evidences()->count() + 1;
        $fileName = 'OC' . $purchaseOrder->id . '-EVID' . $nextNumber . '.' . $extension;
        $filePath = PurchaseOrderEvidence::storeUpload($file, $purchaseOrder->id, $fileName);

        $evidence = PurchaseOrderEvidence::create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_milestone_id' => $milestoneId,
            'purchase_order_invoice_id' => null,
            'uploaded_by' => Auth::id(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => $mime,
            'source' => 'internal',
            'description' => $validated['description'] ?? null,
        ]);

        $this->notification->send([
            'type' => 'PurchaseOrderEvidence',
            'action_by' => Auth::id(),
            'model_action' => 'create',
            'model_id' => $evidence->id,
            'data' => 'subió evidencia ' . $fileName . ' en la OC #' . ($purchaseOrder->folio ?? $purchaseOrder->id),
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Evidencia cargada correctamente.');
    }

    public function download(Request $request, PurchaseOrderEvidence $evidence)
    {
        $disposition = $request->query('disposition') === 'inline' ? 'inline' : 'attachment';
        $response = $evidence->streamFile($disposition);

        abort_unless($response, 404, 'Archivo de evidencia no encontrado.');

        return $response;
    }

    public function destroy(PurchaseOrderEvidence $evidence): RedirectResponse
    {
        $purchaseOrder = $evidence->purchaseOrder;
        $fileName = $evidence->file_name;

        if (PurchaseOrderMilestoneFulfillment::where('evidence_id', $evidence->id)->exists()) {
            return redirect()->route('purchase_orders.show', $purchaseOrder)
                ->with('error', 'La evidencia respalda el cumplimiento de un hito. Revoca la confirmación del hito para poder eliminarla.');
        }

        $evidence->deleteFile();

        $evidence->delete();

        $this->notification->send([
            'type' => 'PurchaseOrderEvidence',
            'action_by' => Auth::id(),
            'model_action' => 'destroy',
            'model_id' => $evidence->id,
            'data' => 'eliminó evidencia ' . $fileName . ' de la OC #' . ($purchaseOrder->folio ?? $purchaseOrder->id),
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Evidencia eliminada correctamente.');
    }
}
