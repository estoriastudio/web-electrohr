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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use Carbon\Carbon;

/* Notificaciones */
use App\Services\NotificationService;
use App\Services\PaymentFolioGenerator;

class PaymentController extends Controller
{
    private const AUTHORIZATION_SELECTION_SESSION_KEY = 'payments.authorization.selection';
    private const PAYABLE_SELECTION_SESSION_KEY = 'payments.payable.selection';
    private const SUPPORTED_CURRENCIES = ['MXN', 'USD', 'EUR'];

    public function __construct(
        private NotificationService $notification,
        private PaymentFolioGenerator $paymentFolioGenerator,
    ) {}

    public function index(Request $request): View
    {
        $urgentDate = Carbon::now()->addDays(7);
        $search     = trim($request->input('search', ''));
        $dueDateFilter = $request->input('due_date_filter', '');
        $dueDateFilter = in_array($dueDateFilter, ['overdue', 'today', 'next_7_days', 'later', 'without_date'], true)
            ? $dueDateFilter
            : '';
        $currency = $this->selectedCurrency($request);
        $paymentCondition = $this->selectedPaymentCondition($request);
        $selectionState = Auth::user()->hasRole('admin')
            ? $this->getAuthorizationSelectionState($request)
            : ['selected_ids' => [], 'selected_count' => 0];

        $payments = Payment::with([
            'milestone.purchaseOrder.supplier',
            'milestone.purchaseOrder.projectRelation',
            'milestone.purchaseOrder.workRelation',
            'milestone.purchaseOrder.milestones:id,purchase_order_id,payment_condition',
        ])
            ->has('milestone.purchaseOrder')
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('projects', 'purchase_orders.project_id', '=', 'projects.id')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('payments.*')
            ->whereIn('payments.status', ['por_autorizar', 'pospuesto'])
            ->where('purchase_orders.status', 'autorizada')
            ->when($currency, fn ($q) => $q->where('purchase_orders.currency', $currency))
            ->when($paymentCondition, fn ($q) => $q->where('purchase_order_milestones.payment_condition', $paymentCondition))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('payments.folio', 'like', '%' . $search . '%')
                        ->orWhere('payments.reference_number', 'like', '%' . $search . '%')
                        ->orWhere('purchase_orders.project', 'like', '%' . $search . '%')
                        ->orWhere('projects.name', 'like', '%' . $search . '%')
                        ->orWhere('suppliers.rfc_name', 'like', '%' . $search . '%')
                        ->orWhere('suppliers.commercial_name', 'like', '%' . $search . '%');
                });
            })
            ->when($dueDateFilter === 'overdue', fn ($q) => $q->whereDate('purchase_order_milestones.due_date', '<', Carbon::today()))
            ->when($dueDateFilter === 'today', fn ($q) => $q->whereDate('purchase_order_milestones.due_date', Carbon::today()))
            ->when($dueDateFilter === 'next_7_days', fn ($q) => $q->whereBetween('purchase_order_milestones.due_date', [Carbon::tomorrow()->startOfDay(), $urgentDate->endOfDay()]))
            ->when($dueDateFilter === 'later', fn ($q) => $q->whereDate('purchase_order_milestones.due_date', '>', $urgentDate))
            ->when($dueDateFilter === 'without_date', fn ($q) => $q->whereNull('purchase_order_milestones.due_date'))
            ->orderByRaw("
                CASE
                    WHEN payments.folio = ? THEN 0
                    WHEN payments.reference_number = ? THEN 0
                    WHEN payments.folio LIKE ? THEN 1
                    WHEN payments.reference_number LIKE ? THEN 1
                    WHEN suppliers.rfc_name = ? THEN 1
                    WHEN suppliers.commercial_name = ? THEN 1
                    WHEN suppliers.rfc_name LIKE ? THEN 2
                    WHEN suppliers.commercial_name LIKE ? THEN 2
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
                $search,
                $search,
                $search . '%',
                $search . '%',
                $urgentDate->toDateString(),
            ])
            ->get();

        return view('payments.index', compact('payments', 'urgentDate', 'search', 'dueDateFilter', 'currency', 'paymentCondition', 'selectionState'));
    }

    public function syncAuthorizationSelection(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'selected_ids' => 'present|array|max:500',
            'selected_ids.*' => 'integer|distinct',
        ]);

        $selectionState = $this->sanitizeAuthorizationSelection($data['selected_ids']);
        $this->persistAuthorizationSelectionState($request, $selectionState);

        return response()->json($selectionState);
    }

    public function clearAuthorizationSelection(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->persistAuthorizationSelectionState($request, [
            'selected_ids' => [],
            'selected_count' => 0,
        ]);

        return response()->json([
            'selected_ids' => [],
            'selected_count' => 0,
        ]);
    }

    public function payable(Request $request): View
    {
        $urgentDate = Carbon::now()->addDays(7);
        $search     = trim($request->input('search', ''));
        $currency   = $this->selectedCurrency($request);
        $paymentCondition = $this->selectedPaymentCondition($request);
        $selectionState = $this->getPayableSelectionState($request);

        $payments = Payment::with([
            'milestone.invoices:id',
            'milestone.purchaseOrder.supplier',
            'milestone.purchaseOrder.projectRelation',
            'milestone.purchaseOrder.workRelation',
        ])
            ->has('milestone.purchaseOrder')
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('payments.*')
            ->where('payments.status', 'autorizado')
            ->when($currency, fn ($q) => $q->where('purchase_orders.currency', $currency))
            ->when($paymentCondition, fn ($q) => $q->where('purchase_order_milestones.payment_condition', $paymentCondition))
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
                purchase_order_milestones.due_date ASC,
                payments.id ASC
            ", [$urgentDate->toDateString()])
            ->paginate(25)
            ->withQueryString();

        $pageTotalsByCurrency = $payments->getCollection()
            ->groupBy(fn (Payment $payment) => $payment->milestone->purchaseOrder->currency)
            ->map(fn ($currencyPayments) => (float) $currencyPayments->sum('amount'))
            ->sortKeys();

        return view('payments.por_pagar', compact(
            'payments',
            'urgentDate',
            'search',
            'currency',
            'paymentCondition',
            'selectionState',
            'pageTotalsByCurrency',
        ));
    }

    public function paid(Request $request): View
    {
        $search           = trim($request->input('search', ''));
        $currency         = $this->selectedCurrency($request);
        $paymentCondition = $this->selectedPaymentCondition($request);

        $payments = Payment::with(['milestone.purchaseOrder.supplier', 'milestone.purchaseOrder.projectRelation', 'milestone.purchaseOrder.workRelation'])
            ->has('milestone.purchaseOrder')
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('payments.*')
            ->where('payments.status', 'pagado')
            ->when($currency, fn ($q) => $q->where('purchase_orders.currency', $currency))
            ->when($paymentCondition, fn ($q) => $q->where('purchase_order_milestones.payment_condition', $paymentCondition))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('payments.folio', 'like', '%' . $search . '%')
                        ->orWhere('payments.reference_number', 'like', '%' . $search . '%')
                        ->orWhere('purchase_orders.folio', 'like', '%' . $search . '%')
                        ->orWhere('suppliers.rfc_name', 'like', '%' . $search . '%')
                        ->orWhere('suppliers.commercial_name', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('payments.payment_date')
            ->orderByDesc('payments.id')
            ->get();

        return view('payments.paid', compact('payments', 'search', 'currency', 'paymentCondition'));
    }

    public function syncPayableSelection(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'selected_ids' => 'present|array|max:500',
            'selected_ids.*' => 'integer|distinct',
        ]);

        $selectionState = $this->sanitizePayableSelection($data['selected_ids']);
        $this->persistPayableSelectionState($request, $selectionState);

        return response()->json($selectionState);
    }

    public function clearPayableSelection(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->persistPayableSelectionState($request, [
            'selected_ids' => [],
            'selected_count' => 0,
        ]);

        return response()->json([
            'selected_ids' => [],
            'selected_count' => 0,
        ]);
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
        $validated = $request->validate([
            'milestone_id'            => 'required|exists:purchase_order_milestones,id',
            'amount'                  => 'required|numeric|min:0.01',
            'payment_date'            => 'required|date',
            'invoice_date'            => 'nullable|date',
            'existing_payment_amount' => 'nullable|numeric|min:0.01',
        ]);

        $milestone = PurchaseOrderMilestone::findOrFail($validated['milestone_id']);
        $orderId = $milestone->purchase_order_id;

        try {
            $payment = DB::transaction(function () use ($validated, $milestone) {
                $milestone = PurchaseOrderMilestone::query()
                    ->with('purchaseOrder')
                    ->lockForUpdate()
                    ->findOrFail($milestone->id);

                if ($milestone->purchaseOrder->status !== 'autorizada') {
                    throw ValidationException::withMessages([
                        'milestone_id' => 'Solo se pueden registrar pagos en una OC autorizada.',
                    ]);
                }

                $payments = $milestone->payments()->lockForUpdate()->orderBy('id')->get();
                $firstPayment = $payments->first();
                $targetAmount = round($milestone->effective_amount, 2);
                $newAmount = round((float) $validated['amount'], 2);

                $canSplitInitialPayment = $payments->count() === 1
                    && $firstPayment?->status === 'por_autorizar';

                if ($canSplitInitialPayment) {
                    if (!array_key_exists('existing_payment_amount', $validated) || is_null($validated['existing_payment_amount'])) {
                        throw ValidationException::withMessages([
                            'existing_payment_amount' => 'Indica el importe corregido del pago inicial para dividir el hito.',
                        ]);
                    }

                    $existingAmount = round((float) $validated['existing_payment_amount'], 2);
                    if (round($existingAmount + $newAmount, 2) > $targetAmount) {
                        throw ValidationException::withMessages([
                            'amount' => 'La suma del pago inicial y el nuevo pago no puede exceder el importe del hito (' . number_format($targetAmount, 2) . ').',
                        ]);
                    }

                    $firstPayment->update(['amount' => $existingAmount]);
                } else {
                    $committedAmount = round((float) $payments
                        ->whereIn('status', ['por_autorizar', 'pagado'])
                        ->sum('amount'), 2);
                    $availableAmount = round($targetAmount - $committedAmount, 2);

                    if ($newAmount > $availableAmount) {
                        throw ValidationException::withMessages([
                            'amount' => 'El importe excede el saldo disponible del hito (' . number_format(max(0, $availableAmount), 2) . ').',
                        ]);
                    }
                }

                return Payment::create([
                    'milestone_id'     => $milestone->id,
                    'folio'            => $this->paymentFolioGenerator->generate(),
                    'amount'           => $newAmount,
                    'payment_date'     => $validated['payment_date'],
                    'invoice_date'     => $validated['invoice_date'] ?? null,
                    'status'           => 'por_autorizar',
                    'reference_number' => null,
                ]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->notification->send([
            'type'         => 'Payment',
            'action_by'    => Auth::id(),
            'model_action' => 'create',
            'model_id'     => $payment->id,
            'data'         => 'registró el pago #' . $payment->folio . ' en el hito #' . $payment->milestone_id . ' de la orden de compra #' . $orderId,
        ]);

        return redirect()->route('purchase_orders.show', $orderId)
            ->with('success', 'Pago registrado para autorización.');
    }

    public function show(Payment $payment)
    {
        return redirect()->route('purchase_orders.show', $payment->milestone->purchase_order_id);
    }

    public function edit(Payment $payment)
    {
        return redirect()->route('purchase_orders.show', $payment->milestone->purchase_order_id);
    }

    public function requestReactivation(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ]);

        $result = DB::transaction(function () use ($payment) {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $milestone = $lockedPayment->milestone()->lockForUpdate()->firstOrFail();
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($milestone->purchase_order_id);

            if ($lockedPayment->status !== 'rechazado') {
                return ['error' => 'Solo se puede solicitar la reactivación de pagos rechazados.'];
            }

            if ($purchaseOrder->status !== 'autorizada') {
                return ['error' => 'La OC debe estar autorizada para solicitar la reactivación de un pago.'];
            }

            $lockedPayment->update(['status' => 'por_autorizar']);

            return [
                'payment' => $lockedPayment,
                'milestone' => $milestone,
                'purchase_order' => $purchaseOrder,
            ];
        });

        if (isset($result['error'])) {
            return redirect()->route('purchase_orders.show', $payment->milestone->purchase_order_id)
                ->with('error', $result['error']);
        }

        $this->notification->send([
            'type' => 'Payment',
            'action_by' => Auth::id(),
            'model_action' => 'request_reactivation',
            'model_id' => $result['payment']->id,
            'data' => 'solicitó reactivar el pago #' . $result['payment']->folio
                . ' del hito #' . $result['milestone']->id
                . ' de la orden de compra #' . $result['purchase_order']->folio
                . '. Motivo: ' . $validated['reason'],
        ]);

        return redirect()->route('purchase_orders.show', $result['purchase_order'])
            ->with('success', 'Solicitud enviada. El pago quedó pendiente de autorización nuevamente.');
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:por_autorizar,autorizado,pagado,rechazado,pospuesto',
            'spei_receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'return_to' => 'nullable|in:purchase_order,payable',
        ]);

        $milestone = $payment->milestone()->firstOrFail();
        $orderId   = $milestone->purchase_order_id;
        $returnTo  = $validated['return_to'] ?? 'purchase_order';

        $hasStatusChange = array_key_exists('status', $validated) && !is_null($validated['status']);
        $hasSpeiFile = $request->hasFile('spei_receipt_file');

        if (!$hasStatusChange && !$hasSpeiFile) {
            return $this->paymentUpdateRedirect($returnTo, $orderId)
                ->with('error', 'No se enviaron cambios para actualizar el pago.');
        }

        if ($hasSpeiFile && $payment->status !== 'autorizado') {
            return $this->paymentUpdateRedirect($returnTo, $orderId)
                ->with('error', 'Solo se puede subir el comprobante SPEI cuando el pago está autorizado.');
        }

        if ($hasSpeiFile) {
            $file = $request->file('spei_receipt_file');
            $safeFolio = Str::upper(preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $payment->folio));
            $fileName = 'SPEI-' . $safeFolio . '-' . time() . '.' . strtolower($file->getClientOriginalExtension());
            $storageDir = 'payments/spei/' . $payment->milestone_id;
            $storedPath = Storage::disk('s3')->putFileAs($storageDir, $file, $fileName);

            if (!$storedPath) {
                return $this->paymentUpdateRedirect($returnTo, $orderId)
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
            $payment->status = 'pagado';
            $payment->save();
            $milestone->increment('covered_amount', $payment->amount);

            $this->notification->send([
                'type'         => 'Payment',
                'action_by'    => Auth::id(),
                'model_action' => 'update',
                'model_id'     => $payment->id,
                'data'         => 'registró el comprobante SPEI y marcó como pagado el pago #' . $payment->folio . ' en el hito #' . $milestone->id . ' de la orden de compra #' . $orderId,
            ]);

            return $this->paymentUpdateRedirect($returnTo, $orderId)
                ->with('success', 'Comprobante SPEI registrado y pago marcado como pagado.');
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
            return $this->paymentUpdateRedirect($returnTo, $orderId)
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

        return $this->paymentUpdateRedirect($returnTo, $orderId)
            ->with('success', 'Estatus del pago actualizado.');
    }

    private function paymentUpdateRedirect(string $returnTo, int $orderId): RedirectResponse
    {
        return $returnTo === 'payable'
            ? redirect()->route('payments.payable')
            : redirect()->route('purchase_orders.show', $orderId);
    }

    public function markMultiplePaidWithSpei(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_ids' => 'required|array|min:1|max:500',
            'payment_ids.*' => 'integer|distinct|exists:payments,id',
            'spei_receipt_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $paymentIds = collect($validated['payment_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $payments = Payment::query()
            ->whereIn('id', $paymentIds)
            ->where('status', 'autorizado')
            ->orderBy('id')
            ->get();

        if ($payments->count() !== $paymentIds->count()) {
            throw ValidationException::withMessages([
                'payment_ids' => 'Todos los pagos seleccionados deben seguir en estatus autorizado.',
            ]);
        }

        $file = $request->file('spei_receipt_file');
        $safeFolio = Str::upper(preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $payments->first()->folio));
        $fileName = 'SPEI-MULTIPLE-' . $safeFolio . '-' . time() . '.' . strtolower($file->getClientOriginalExtension());
        $storedPath = Storage::disk('s3')->putFileAs('payments/spei/multiple', $file, $fileName);

        if (!$storedPath) {
            return redirect()->route('payments.payable')
                ->with('error', 'No se pudo guardar el comprobante SPEI en S3. Intenta nuevamente.');
        }

        try {
            $updatedPayments = DB::transaction(function () use ($paymentIds, $storedPath, $fileName) {
                $payments = Payment::query()
                    ->whereIn('id', $paymentIds)
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                if ($payments->count() !== $paymentIds->count() || $payments->contains(fn (Payment $payment) => $payment->status !== 'autorizado')) {
                    throw ValidationException::withMessages([
                        'payment_ids' => 'Todos los pagos seleccionados deben seguir en estatus autorizado.',
                    ]);
                }

                $milestones = PurchaseOrderMilestone::query()
                    ->whereIn('id', $payments->pluck('milestone_id')->unique())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($payments as $payment) {
                    $payment->update([
                        'status' => 'pagado',
                        'spei_receipt_path' => $storedPath,
                        'spei_receipt_name' => $fileName,
                    ]);
                }

                $payments->groupBy('milestone_id')->each(function ($milestonePayments, $milestoneId) use ($milestones) {
                    $milestones->get($milestoneId)->increment('covered_amount', $milestonePayments->sum('amount'));
                });

                return $payments->load('milestone');
            });
        } catch (\Throwable $exception) {
            Storage::disk('s3')->delete($storedPath);

            throw $exception;
        }

        foreach ($updatedPayments as $payment) {
            $milestone = $payment->milestone;
            $this->notification->send([
                'type' => 'Payment',
                'action_by' => Auth::id(),
                'model_action' => 'update',
                'model_id' => $payment->id,
                'data' => 'registró el comprobante SPEI y marcó como pagado el pago #' . $payment->folio . ' en el hito #' . $milestone->id . ' de la orden de compra #' . $milestone->purchase_order_id,
            ]);
        }

        $this->persistPayableSelectionState($request, [
            'selected_ids' => [],
            'selected_count' => 0,
        ]);

        return redirect()->route('payments.payable')
            ->with('success', 'Comprobante SPEI asociado y ' . $updatedPayments->count() . ' pago(s) marcado(s) como pagado(s).');
    }

    public function authorizeMultiple(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_ids' => 'required|array|min:1|max:500',
            'payment_ids.*' => 'integer|distinct|exists:payments,id',
        ]);

        $paymentIds = collect($validated['payment_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $authorizedPayments = DB::transaction(function () use ($paymentIds) {
            $payments = Payment::query()
                ->whereIn('id', $paymentIds)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $milestones = PurchaseOrderMilestone::query()
                ->whereIn('id', $payments->pluck('milestone_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $purchaseOrders = PurchaseOrder::query()
                ->whereIn('id', $milestones->pluck('purchase_order_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $hasInvalidPayment = $payments->count() !== $paymentIds->count()
                || $payments->contains(function (Payment $payment) use ($milestones, $purchaseOrders) {
                    $milestone = $milestones->get($payment->milestone_id);
                    $purchaseOrder = $milestone ? $purchaseOrders->get($milestone->purchase_order_id) : null;

                    return !in_array($payment->status, ['por_autorizar', 'pospuesto'], true)
                        || !$purchaseOrder
                        || $purchaseOrder->status !== 'autorizada';
                });

            if ($hasInvalidPayment) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'Todos los pagos seleccionados deben seguir pendientes y pertenecer a una OC autorizada.',
                ]);
            }

            foreach ($payments as $payment) {
                $payment->update(['status' => 'autorizado']);
            }

            return $payments->map(function (Payment $payment) use ($milestones) {
                return [
                    'id' => $payment->id,
                    'folio' => $payment->folio,
                    'milestone_id' => $payment->milestone_id,
                    'purchase_order_id' => $milestones->get($payment->milestone_id)->purchase_order_id,
                ];
            });
        });

        foreach ($authorizedPayments as $payment) {
            $this->notification->send([
                'type' => 'Payment',
                'action_by' => Auth::id(),
                'model_action' => 'update',
                'model_id' => $payment['id'],
                'data' => 'autorizó el pago #' . $payment['folio'] . ' del hito #' . $payment['milestone_id'] . ' de la orden de compra #' . $payment['purchase_order_id'],
            ]);
        }

        $this->persistAuthorizationSelectionState($request, [
            'selected_ids' => [],
            'selected_count' => 0,
        ]);

        return redirect()->route('payments.index')
            ->with('success', $authorizedPayments->count() . ' pago(s) autorizado(s).');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $milestone = $payment->milestone;
        $orderId   = $milestone->purchase_order_id;

        $hasSharedSpeiReceipt = $payment->spei_receipt_path
            && Payment::whereKeyNot($payment->id)
                ->where('spei_receipt_path', $payment->spei_receipt_path)
                ->exists();

        if ($payment->spei_receipt_path && !$hasSharedSpeiReceipt && Storage::disk('s3')->exists($payment->spei_receipt_path)) {
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

    private function selectedCurrency(Request $request): string
    {
        $currency = strtoupper(trim((string) $request->input('currency', '')));

        return in_array($currency, self::SUPPORTED_CURRENCIES, true) ? $currency : '';
    }

    private function selectedPaymentCondition(Request $request): string
    {
        $paymentCondition = trim((string) $request->input('payment_condition', ''));

        return in_array($paymentCondition, ['credito', 'contado'], true) ? $paymentCondition : '';
    }

    private function getAuthorizationSelectionState(Request $request): array
    {
        $selectionState = $this->sanitizeAuthorizationSelection(
            $request->session()->get(self::AUTHORIZATION_SELECTION_SESSION_KEY . '.selected_ids', [])
        );

        $this->persistAuthorizationSelectionState($request, $selectionState);

        return $selectionState;
    }

    private function sanitizeAuthorizationSelection(array $ids): array
    {
        $normalizedIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return ['selected_ids' => [], 'selected_count' => 0];
        }

        $allowedPaymentIds = Payment::query()
            ->join('purchase_order_milestones', 'payments.milestone_id', '=', 'purchase_order_milestones.id')
            ->join('purchase_orders', 'purchase_order_milestones.purchase_order_id', '=', 'purchase_orders.id')
            ->whereIn('payments.id', $normalizedIds)
            ->whereIn('payments.status', ['por_autorizar', 'pospuesto'])
            ->where('purchase_orders.status', 'autorizada')
            ->pluck('payments.id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $selectedIds = $normalizedIds
            ->filter(fn ($id) => $allowedPaymentIds->has($id))
            ->values()
            ->all();

        return [
            'selected_ids' => $selectedIds,
            'selected_count' => count($selectedIds),
        ];
    }

    private function persistAuthorizationSelectionState(Request $request, array $selectionState): void
    {
        if (empty($selectionState['selected_ids'])) {
            $request->session()->forget(self::AUTHORIZATION_SELECTION_SESSION_KEY);
            return;
        }

        $request->session()->put(self::AUTHORIZATION_SELECTION_SESSION_KEY, [
            'selected_ids' => array_values(array_map('intval', $selectionState['selected_ids'])),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function getPayableSelectionState(Request $request): array
    {
        $selectionState = $this->sanitizePayableSelection(
            $request->session()->get(self::PAYABLE_SELECTION_SESSION_KEY . '.selected_ids', [])
        );

        $this->persistPayableSelectionState($request, $selectionState);

        return $selectionState;
    }

    private function sanitizePayableSelection(array $ids): array
    {
        $normalizedIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return [
                'selected_ids' => [],
                'selected_count' => 0,
            ];
        }

        $allowedPayments = Payment::query()
            ->whereIn('id', $normalizedIds)
            ->where('status', 'autorizado')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $selectedIds = $normalizedIds
            ->filter(fn ($id) => $allowedPayments->has($id))
            ->values()
            ->all();

        return [
            'selected_ids' => $selectedIds,
            'selected_count' => count($selectedIds),
        ];
    }

    private function persistPayableSelectionState(Request $request, array $selectionState): void
    {
        if (empty($selectionState['selected_ids'])) {
            $request->session()->forget(self::PAYABLE_SELECTION_SESSION_KEY);
            return;
        }

        $request->session()->put(self::PAYABLE_SELECTION_SESSION_KEY, [
            'selected_ids' => array_values(array_map('intval', $selectionState['selected_ids'])),
            'updated_at' => now()->toIso8601String(),
        ]);
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
