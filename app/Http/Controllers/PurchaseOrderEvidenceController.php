<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvidence;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
        $directory = 'evidences/' . $purchaseOrder->id;
        $filePath = $file->storeAs($directory, $fileName);

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
        if (!$evidence->file_path || !Storage::exists($evidence->file_path)) {
            abort(404, 'Archivo de evidencia no encontrado.');
        }

        $downloadName = str_replace('"', '', (string) ($evidence->file_name ?: basename($evidence->file_path)));
        $mime = $evidence->mime_type ?: 'application/octet-stream';
        $disposition = $request->query('disposition') === 'inline' ? 'inline' : 'attachment';

        return response()->file(
            Storage::path($evidence->file_path),
            [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
            ]
        );
    }

    public function destroy(PurchaseOrderEvidence $evidence): RedirectResponse
    {
        $purchaseOrder = $evidence->purchaseOrder;
        $fileName = $evidence->file_name;

        if ($evidence->file_path && Storage::exists($evidence->file_path)) {
            Storage::delete($evidence->file_path);
        }

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
