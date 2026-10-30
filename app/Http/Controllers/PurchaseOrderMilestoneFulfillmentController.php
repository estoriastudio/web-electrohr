<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvidence;
use App\Models\PurchaseOrderMilestoneFulfillment;
use App\Services\MilestoneCreditTerms;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseOrderMilestoneFulfillmentController extends Controller
{
    public function __construct(
        private NotificationService $notification,
        private MilestoneCreditTerms $creditTerms,
    ) {}

    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->ensureOpen();

        $user = Auth::user();
        $canRecord = $user->hasRole('admin')
            || ($user->hasRole('Orden de compra') && (int) $purchaseOrder->buyer_id === (int) $user->id);
        abort_unless($canRecord, 403);

        if ($purchaseOrder->status !== 'autorizada') {
            return back()->with('error', 'Solo se puede registrar el cumplimiento de una OC autorizada.');
        }

        $validated = $request->validate([
            'fulfillment_type' => ['required', Rule::in(PurchaseOrderMilestoneFulfillment::RECORDED_TYPES)],
            'milestone_id' => [
                'required',
                'integer',
                Rule::exists('purchase_order_milestones', 'id')->where('purchase_order_id', $purchaseOrder->id),
            ],
            'comment' => 'nullable|string|max:1000',
            'fulfillment_evidence' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'fulfillment_type.required' => 'Selecciona el tipo de cumplimiento.',
            'fulfillment_type.in' => 'El tipo de cumplimiento no es válido.',
            'milestone_id.required' => 'Selecciona el hito que se cumple.',
            'milestone_id.exists' => 'El hito seleccionado no pertenece a la OC.',
            'comment.max' => 'El comentario admite máximo 1000 caracteres.',
            'fulfillment_evidence.required' => 'Adjunta la evidencia del cumplimiento.',
        ]);

        $type = $validated['fulfillment_type'];
        $typeLabel = PurchaseOrderMilestoneFulfillment::TYPE_LABELS[$type];
        $comment = $type === PurchaseOrderMilestoneFulfillment::TYPE_ADVANCE
            ? null
            : ($validated['comment'] ?? null);
        $storedPath = null;
        $creditWarning = null;

        try {
            $milestone = DB::transaction(function () use ($request, $purchaseOrder, $validated, $type, $typeLabel, $comment, &$storedPath, &$creditWarning) {
                $milestone = $purchaseOrder->milestones()->whereKey($validated['milestone_id'])->lockForUpdate()->firstOrFail();

                if ($milestone->deliveries()->exists()) {
                    throw ValidationException::withMessages([
                        'milestone_id' => 'El hito ya tiene entregas físicas ligadas; se cumple por Recepción física.',
                    ]);
                }

                if ($milestone->fulfillments()->exists()) {
                    throw ValidationException::withMessages([
                        'milestone_id' => 'El hito ya tiene un registro de cumplimiento.',
                    ]);
                }

                if ($milestone->delivery_confirmed_at !== null) {
                    throw ValidationException::withMessages([
                        'milestone_id' => 'El cumplimiento del hito ya está verificado.',
                    ]);
                }

                $file = $request->file('fulfillment_evidence');
                $nextNumber = $purchaseOrder->evidences()->count() + 1;
                $extension = strtolower($file->getClientOriginalExtension());
                $evidenceFileName = 'OC' . $purchaseOrder->id . '-EVID' . $nextNumber . '.' . $extension;
                $storedPath = PurchaseOrderEvidence::storeUpload($file, $purchaseOrder->id, $evidenceFileName);

                $evidence = PurchaseOrderEvidence::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_milestone_id' => $milestone->id,
                    'uploaded_by' => Auth::id(),
                    'file_name' => $evidenceFileName,
                    'file_path' => $storedPath,
                    'mime_type' => $file->getClientMimeType(),
                    'source' => 'internal',
                    'description' => 'Evidencia de cumplimiento (' . $typeLabel . ')',
                ]);

                PurchaseOrderMilestoneFulfillment::create([
                    'purchase_order_milestone_id' => $milestone->id,
                    'fulfillment_type' => $type,
                    'evidence_id' => $evidence->id,
                    'comment' => $comment,
                    'recorded_by' => Auth::id(),
                    'recorded_at' => now(),
                ]);

                $milestone->update([
                    'delivery_confirmed_at' => now(),
                    'delivery_confirmed_by' => Auth::id(),
                ]);

                $creditWarning = $this->creditTerms->apply($milestone->load('purchaseOrder'));

                return $milestone;
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk(PurchaseOrderEvidence::STORAGE_DISK)->delete($storedPath);
            }

            throw $e;
        }

        $this->notification->send([
            'type'         => 'PurchaseOrder',
            'action_by'    => Auth::id(),
            'model_action' => 'update',
            'model_id'     => $purchaseOrder->id,
            'data'         => 'validó el cumplimiento (' . $typeLabel . ') del hito #' . $milestone->id
                . ' en la orden de compra #' . ($purchaseOrder->folio ?? $purchaseOrder->id) . '.',
        ]);

        return redirect()->route('purchase_orders.show', $purchaseOrder)
            ->with('success', 'Cumplimiento validado (' . $typeLabel . ').')
            ->with('warning', $creditWarning);
    }
}
