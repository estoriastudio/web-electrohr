<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/* Modelos */
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMilestone;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Carbon\Carbon;

/* Notificaciones */
use App\Services\NotificationService;

class PaymentController extends Controller
{
    public function __construct(private NotificationService $notification) {}

    public function index(Request $request): View
    {
        $urgentDate = Carbon::now()->addDays(7);
        $search     = trim($request->input('search', ''));

        $payments = Payment::with(['milestone.purchaseOrder.supplier', 'milestone.purchaseOrder.projectRelation', 'milestone.purchaseOrder.workRelation'])
            ->has('milestone.purchaseOrder')
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
            ->select('payments.*')
            ->where('payments.status', 'por_autorizar')
            ->where('purchase_orders.status', 'autorizada')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('payments.folio', 'like', '%' . $search . '%')
                        ->orWhere('payments.reference_number', 'like', '%' . $search . '%');
                });
            })
            ->orderByRaw("
                CASE
                    WHEN payments.folio = ? THEN 0
                    WHEN payments.reference_number = ? THEN 0
                    WHEN payments.folio LIKE ? THEN 1
                    WHEN payments.reference_number LIKE ? THEN 1
                    ELSE 2
                END ASC,
                CASE
                    WHEN purchase_order_milestones.due_date <= ? THEN 0
                    ELSE 1
                END ASC,
                purchase_order_milestones.due_date ASC
            ", [
                $search,
                $search,
                $search . '%',
                $search . '%',
                $urgentDate->toDateString(),
            ])
            ->get();

        return view('payments.index', compact('payments', 'urgentDate', 'search'));
    }

    public function payable(Request $request): View
    {
        $urgentDate = Carbon::now()->addDays(7);
        $search     = trim($request->input('search', ''));

        $payments = Payment::with(['milestone.purchaseOrder.supplier', 'milestone.purchaseOrder.projectRelation', 'milestone.purchaseOrder.workRelation'])
            ->has('milestone.purchaseOrder')
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('payments.*')
            ->where('payments.status', 'autorizado')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('payments.folio', 'like', '%' . $search . '%')
                        ->orWhere('payments.reference_number', 'like', '%' . $search . '%')
                        ->orWhere('purchase_orders.folio', 'like', '%' . $search . '%')
                        ->orWhere('suppliers.rfc_name', 'like', '%' . $search . '%')
                        ->orWhere('suppliers.commercial_name', 'like', '%' . $search . '%');
                });
            })
            ->orderByRaw("
                CASE
                    WHEN purchase_order_milestones.due_date <= ? THEN 0
                    ELSE 1
                END ASC,
                purchase_order_milestones.due_date ASC
            ", [$urgentDate->toDateString()])
            ->get();

        return view('payments.por_pagar', compact('payments', 'urgentDate', 'search'));
    }

    public function create()
    {
        return redirect()->route('payments.index');
    }

    public function interactive(): View
    {
        $urgentDate = Carbon::now()->addDays(7);

        $payments = Payment::with(['milestone.purchaseOrder.supplier', 'milestone.purchaseOrder.projectRelation', 'milestone.purchaseOrder.workRelation'])
            ->has('milestone.purchaseOrder')
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
            ->select('payments.*')
            ->whereIn('payments.status', ['por_autorizar', 'pospuesto'])
            ->where('purchase_orders.status', 'autorizada')
            ->orderByRaw("
                CASE
                    WHEN payments.status = 'pospuesto' THEN 1
                    ELSE 0
                END ASC,
                CASE
                    WHEN purchase_order_milestones.due_date <= ? THEN 0
                    ELSE 1
                END ASC,
                purchase_order_milestones.due_date ASC
            ", [$urgentDate->toDateString()])
            ->get();

        return view('payments.interactive', compact('payments', 'urgentDate'));
    }

    public function swipe(Request $request, Payment $payment): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:autorizado,rechazado,pospuesto,por_autorizar',
        ]);

        $payment->update(['status' => $validated['status']]);

        // Solo notificar si no es un undo (revertir a por_autorizar)
        if (!in_array($validated['status'], ['por_autorizar', 'pospuesto'])) {
            $milestone = $payment->milestone;
            $this->notification->send([
                'type'         => 'Payment',
                'action_by'    => Auth::id(),
                'model_action' => 'update',
                'model_id'     => $payment->id,
                'data'         => $validated['status'] === 'autorizado'
                    ? 'autorizó el pago #' . $payment->folio . ' del hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id
                    : 'rechazó el pago #' . $payment->folio . ' del hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'status'  => $validated['status'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $milestoneId = $request->input('milestone_id');
        $milestone = $milestoneId ? PurchaseOrderMilestone::find($milestoneId) : null;

        if ($milestone) {
            return redirect()->route('purchase_orders.show', $milestone->purchase_order_id)
                ->with('error', 'Los pagos se generan automáticamente al crear el hito. No se permite crear pagos manuales.');
        }

        return redirect()->route('payments.index')
            ->with('error', 'Los pagos se generan automáticamente al crear el hito. No se permite crear pagos manuales.');
    }

    public function show(Payment $payment)
    {
        return redirect()->route('purchase_orders.show', $payment->milestone->purchase_order_id);
    }

    public function edit(Payment $payment)
    {
        return redirect()->route('purchase_orders.show', $payment->milestone->purchase_order_id);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:por_autorizar,autorizado,pagado,rechazado,pospuesto',
            'spei_receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $milestone = $payment->milestone()->firstOrFail();
        $orderId   = $milestone->purchase_order_id;

        $hasStatusChange = array_key_exists('status', $validated) && !is_null($validated['status']);
        $hasSpeiFile = $request->hasFile('spei_receipt_file');

        if (!$hasStatusChange && !$hasSpeiFile) {
            return redirect()->route('purchase_orders.show', $orderId)
                ->with('error', 'No se enviaron cambios para actualizar el pago.');
        }

        if ($hasSpeiFile) {
            $file = $request->file('spei_receipt_file');
            $safeFolio = Str::upper(preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $payment->folio));
            $fileName = 'SPEI-' . $safeFolio . '-' . time() . '.' . strtolower($file->getClientOriginalExtension());
            $storageDir = 'payments/spei/' . $payment->milestone_id;
            $storedPath = Storage::disk('s3')->putFileAs($storageDir, $file, $fileName);

            if (!$storedPath) {
                return redirect()->route('purchase_orders.show', $orderId)
                    ->with('error', 'No se pudo guardar el comprobante SPEI en S3. Intenta nuevamente.');
            }

            $previousSpeiPath = $payment->spei_receipt_path;

            if ($previousSpeiPath && Storage::disk('s3')->exists($previousSpeiPath)) {
                Storage::disk('s3')->delete($previousSpeiPath);
            }

            $payment->spei_receipt_path = $storedPath;
            $payment->spei_receipt_name = $fileName;
        }

        if (!$hasStatusChange) {
            $payment->save();

            $this->notification->send([
                'type'         => 'Payment',
                'action_by'    => Auth::id(),
                'model_action' => 'update',
                'model_id'     => $payment->id,
                'data'         => 'subió/actualizó el comprobante SPEI del pago #' . $payment->folio . ' en el hito #' . $milestone->id . ' de la orden de compra #' . $orderId,
            ]);

            return redirect()->route('purchase_orders.show', $orderId)
                ->with('success', 'Comprobante SPEI actualizado correctamente.');
        }

        $previousStatus = $payment->status;
        $newStatus      = $validated['status'];

        // Reglas de transición de estatus (no se puede ir "hacia atrás")
        $isAdmin = Auth::user()->hasRole('admin');

        $allowed = match ($previousStatus) {
            'por_autorizar' => $isAdmin ? ['autorizado', 'rechazado', 'pospuesto'] : [],
            'pospuesto'     => $isAdmin ? ['por_autorizar', 'autorizado', 'rechazado'] : [],
            'autorizado'    => $isAdmin ? ['pagado', 'por_autorizar'] : ['pagado'],
            'pagado'        => [],                                      // pagado es estado final
            'rechazado'     => $isAdmin ? ['por_autorizar'] : [],       // solo admin puede reactivar
            default         => [],
        };

        if (!in_array($newStatus, $allowed)) {
            return redirect()->route('purchase_orders.show', $orderId)
                ->with('error', 'Transición de estatus no permitida.');
        }

        $payment->status = $newStatus;
        $payment->save();

        // Ajustar saldo cubierto del hito según transición
        if ($previousStatus !== 'pagado' && $newStatus === 'pagado') {
            $milestone->increment('covered_amount', $payment->amount);
        } elseif ($previousStatus === 'pagado' && $newStatus !== 'pagado') {
            $milestone->decrement('covered_amount', $payment->amount);
        }

        // Notificación
        if ($newStatus !== 'pospuesto') {
            $this->notification->send([
                'type'         => 'Payment',
                'action_by'    => Auth::id(),
                'model_action' => 'update',
                'model_id'     => $payment->id,
                'data'         => 'actualizó el estatus del pago #' . $payment->folio . ' a «' . $newStatus . '» en el hito #' . $milestone->id . ' de la orden de compra #' . $orderId,
            ]);
        }

        return redirect()->route('purchase_orders.show', $orderId)
            ->with('success', 'Estatus del pago actualizado.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $milestone = $payment->milestone;
        $orderId   = $milestone->purchase_order_id;

        if ($payment->spei_receipt_path && Storage::disk('s3')->exists($payment->spei_receipt_path)) {
            Storage::disk('s3')->delete($payment->spei_receipt_path);
        }

        if ($payment->status === 'pagado') {
            $milestone->decrement('covered_amount', $payment->amount);
        }

        $payment->delete();

        // Notificación
        $this->notification->send([
            'type'         => 'Payment',
            'action_by'    => Auth::id(),
            'model_action' => 'destroy',
            'model_id'     => $payment->id,
            'data'         => 'eliminó un pago en el hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id,
        ]);

        return redirect()->route('purchase_orders.show', $orderId)
            ->with('success', 'Pago eliminado.');
    }

    /**
     * Descargar o visualizar comprobante SPEI asociado al pago.
     */
    public function downloadSpeiReceipt(Request $request, Payment $payment)
    {
        $disk = Storage::disk('s3');

        if (!$payment->spei_receipt_path || !$disk->exists($payment->spei_receipt_path)) {
            abort(404, 'Comprobante SPEI no encontrado.');
        }

        $downloadName = $payment->spei_receipt_name ?: basename($payment->spei_receipt_path);
        $downloadName = str_replace('"', '', $downloadName);
        $disposition = $request->query('disposition') === 'inline' ? 'inline' : 'attachment';

        $url = method_exists($disk, 'temporaryUrl')
            ? $disk->temporaryUrl(
                $payment->spei_receipt_path,
                now()->addMinutes(10),
                ['ResponseContentDisposition' => $disposition . '; filename="' . $downloadName . '"']
            )
            : $disk->url($payment->spei_receipt_path);

        return redirect()->away($url);
    }

    /**
     * Genera y descarga el PDF de contrarecibo de un pago (solo hitos crédito).
     */
    public function contrarecibo(Payment $payment)
    {
        $payment->load([
            'milestone.purchaseOrder.supplier',
            'milestone.purchaseOrder.projectRelation',
        ]);

        $milestone     = $payment->milestone;
        $purchaseOrder = $milestone->purchaseOrder;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.contrarecibo_pdf', compact('payment', 'milestone', 'purchaseOrder'));
        $pdf->setPaper('letter', 'portrait');

        $fileName = 'contrarecibo-' . $payment->folio . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Vista de Alta de Facturas (acceso rápido para perfil payments).
     */
    public function altaFacturas(Request $request): \Illuminate\View\View
    {
        $search = trim($request->input('search', ''));
        $poType = $request->input('po_type', '');

        $orders = PurchaseOrder::with(['supplier', 'milestones', 'invoices'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('supplier', function ($s) use ($search) {
                        $s->where('rfc_name', 'like', '%' . $search . '%')
                          ->orWhere('commercial_name', 'like', '%' . $search . '%');
                    })->orWhere('folio', 'like', '%' . $search . '%');
                });
            })
            ->when(in_array($poType, ['materiales_servicios', 'mantenimiento']), function ($q) use ($poType) {
                $q->where('type', $poType);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('payments.alta_facturas', compact('orders', 'search', 'poType'));
    }
}
